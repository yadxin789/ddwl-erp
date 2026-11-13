<?php

namespace App\Console\Commands;

use App\Models\Ai\AiAttachment;
use App\Models\Bom\BomImport;
use App\Models\Organization\User;
use App\Models\Rnd\RndCollabDocument;
use App\Models\System\MediaFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 物理文件巡检（兜底保障）。
 *
 * 双向核对数据库引用和受管目录：
 *   1. 磁盘有、数据库无引用 → 孤儿文件，--fix 时删除（带宽限期）
 *   2. 数据库有、磁盘无文件 → 死记录，--purge-dead 时清理
 *   3. 协同文档历史版本按 file_version_keep 保留，超出的旧版本 --fix 时删除
 *   4. 空目录清理，避免目录树无限膨胀
 *
 * 只扫描本系统上传目录，不触碰模板、备份等非受管文件。
 */
class AuditFiles extends Command
{
    protected $signature = 'files:gc
                            {--fix : 实际清理孤儿文件、旧历史版本与空目录（不加则只检查）}
                            {--purge-dead : 同时删除「库中有记录、磁盘无文件」的死记录}
                            {--grace=60 : 孤儿文件宽限期（分钟）}';

    protected $description = '物理文件巡检：清理孤儿文件、旧历史版本与空目录，核对记录与文件';

    /** 受管目录：只处理这些前缀，避免误删模板/备份。 */
    protected const MANAGED = [
        'local' => ['uploads'],
        'file_repository' => ['collab'],
        'public' => ['avatars'],
    ];

    protected int $graceMinutes = 60;

    public function handle(): int
    {
        $this->graceMinutes = max(0, (int) $this->option('grace'));
        $fix = (bool) $this->option('fix');
        $purgeDead = (bool) $this->option('purge-dead');

        $this->info(($fix ? '[清理模式] ' : '[检查模式] ') . '开始巡检物理文件…');

        $references = $this->buildReferences();
        [$orphans, $missing] = $this->scan($references);

        $orphanCount = array_sum(array_map('count', $orphans));
        $missingCount = array_sum(array_map('count', $missing));

        $this->report('孤儿文件（磁盘有、库中无引用）', $orphans, $orphanCount);
        $this->report('死记录（库中有引用、磁盘无文件）', $missing, $missingCount);

        if (! $fix && ! $purgeDead) {
            $this->newLine();
            $this->warn('以上仅为检查结果，未做任何修改。');
            $this->line('  清理孤儿文件与空目录：php artisan files:gc --fix');
            $this->line('  同时清理死记录：      php artisan files:gc --fix --purge-dead');

            return Command::SUCCESS;
        }

        if ($fix) {
            $this->purgeOrphans($orphans);
            $this->pruneCollabHistory();
            $this->cleanEmptyDirs();
        }

        if ($purgeDead) {
            $this->purgeDeadRecords($missing);
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function buildReferences(): array
    {
        $refs = [
            'local' => [],
            'file_repository' => [],
            'public' => [],
        ];

        $add = function (string $disk, ?string $path) use (&$refs) {
            $path = trim((string) $path);
            if ($path !== '') {
                $refs[$disk][$path] = true;
            }
        };

        // media_files 是通用文件表，历史上可能落在 local 或 public
        foreach (MediaFile::withTrashed()->pluck('file_path') as $path) {
            $add('local', $path);
            $add('public', $path);
        }

        // AI 附件与 BOM 导入文件都在 local 盘
        foreach (AiAttachment::query()->pluck('file_path') as $path) {
            $add('local', $path);
        }
        foreach (BomImport::query()->pluck('file_path') as $path) {
            $add('local', $path);
        }

        // 协同文档正文与历史版本
        foreach (RndCollabDocument::withTrashed()->get() as $doc) {
            $add('file_repository', $doc->storage_path);

            $historyDir = 'collab/' . $doc->project_id . '/history';
            if (Storage::disk('file_repository')->exists($historyDir)) {
                foreach (Storage::disk('file_repository')->files($historyDir) as $path) {
                    if (str_starts_with(basename($path), 'doc_' . $doc->id . '_v')) {
                        $add('file_repository', $path);
                    }
                }
            }
        }

        // 用户自定义头像（默认头像在 public/avatars/defaults，不属于 storage）
        foreach (User::query()->whereNotNull('avatar')->pluck('avatar') as $avatar) {
            $avatar = trim((string) $avatar);
            if ($avatar !== '' && ! str_starts_with($avatar, 'defaults/')) {
                $add('public', 'avatars/' . $avatar);
            }
        }

        return $refs;
    }

    /**
     * @param array<string, array<string, bool>> $refs
     * @return array{0: array<string, array<int, string>>, 1: array<string, array<int, string>>}
     */
    private function scan(array $refs): array
    {
        $orphans = [];
        $missing = [];

        foreach (self::MANAGED as $diskName => $directories) {
            $disk = Storage::disk($diskName);

            foreach ($directories as $directory) {
                if (! $disk->exists($directory)) {
                    continue;
                }

                foreach ($disk->allFiles($directory) as $path) {
                    if (! isset($refs[$diskName][$path])) {
                        $orphans[$diskName][] = $path;
                    }
                }
            }
        }

        foreach (MediaFile::withTrashed()->get() as $file) {
            $path = trim((string) $file->file_path);
            if ($path === '') {
                continue;
            }

            if (! Storage::disk('local')->exists($path) && ! Storage::disk('public')->exists($path)) {
                $missing['media_files'][] = $path . ' (MediaFile#' . $file->id . ')';
            }
        }

        foreach (RndCollabDocument::withTrashed()->get() as $doc) {
            $path = trim((string) $doc->storage_path);
            if ($path !== '' && ! Storage::disk('file_repository')->exists($path)) {
                $missing['collab_documents'][] = $path . ' (RndCollabDocument#' . $doc->id . ')';
            }
        }

        return [$orphans, $missing];
    }

    /** @param array<string, array<int, string>> $items */
    private function report(string $title, array $items, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $this->newLine();
        $this->warn("{$title}：{$count} 个");
        foreach ($items as $group => $paths) {
            $this->line('  [' . $group . ']');
            foreach (array_slice($paths, 0, 20) as $path) {
                $this->line('    - ' . $path);
            }
            if (count($paths) > 20) {
                $this->line('    … 其余 ' . (count($paths) - 20) . ' 个');
            }
        }
    }

    /** @param array<string, array<int, string>> $orphans */
    private function purgeOrphans(array $orphans): void
    {
        $deleted = 0;
        $skippedFresh = 0;
        $failed = 0;

        foreach ($orphans as $diskName => $paths) {
            $disk = Storage::disk($diskName);

            foreach ($paths as $path) {
                $age = $this->ageInMinutes($disk, $path);
                if ($age !== null && $age < $this->graceMinutes) {
                    $skippedFresh++;
                    continue;
                }

                try {
                    $disk->delete($path);
                    $deleted++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("  ✗ 删除失败 {$diskName}:{$path} — {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info("孤儿文件清理：删除 {$deleted} 个，宽限期跳过 {$skippedFresh} 个，失败 {$failed} 个。");

        Log::info('文件巡检清理孤儿', [
            'deleted' => $deleted,
            'skipped_fresh' => $skippedFresh,
            'failed' => $failed,
        ]);
    }

    private function pruneCollabHistory(): void
    {
        $keep = max(1, (int) config('trash.file_version_keep', 3));
        $deleted = 0;

        foreach (RndCollabDocument::query()->get() as $doc) {
            $historyDir = 'collab/' . $doc->project_id . '/history';
            if (! Storage::disk('file_repository')->exists($historyDir)) {
                continue;
            }

            $versions = [];
            foreach (Storage::disk('file_repository')->files($historyDir) as $path) {
                $name = basename($path);
                if (! preg_match('/^doc_' . $doc->id . '_v(\d+)\./', $name, $matches)) {
                    continue;
                }
                $versions[(int) $matches[1]] = $path;
            }

            krsort($versions);
            $toDelete = array_slice($versions, $keep, null, true);
            foreach ($toDelete as $path) {
                Storage::disk('file_repository')->delete($path);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("协同文档历史版本清理：删除 {$deleted} 个（每份文档保留最近 {$keep} 版）。");
            Log::info('协同文档历史版本清理', ['deleted' => $deleted, 'keep' => $keep]);
        }
    }

    /** @param array<string, array<int, string>> $missing */
    private function purgeDeadRecords(array $missing): void
    {
        $deleted = 0;

        foreach ($missing['media_files'] ?? [] as $entry) {
            if (preg_match('/MediaFile#(\d+)/', $entry, $matches)) {
                $file = MediaFile::withTrashed()->find((int) $matches[1]);
                if ($file) {
                    $file->forceDelete();
                    $deleted++;
                }
            }
        }

        foreach ($missing['collab_documents'] ?? [] as $entry) {
            if (preg_match('/RndCollabDocument#(\d+)/', $entry, $matches)) {
                $doc = RndCollabDocument::withTrashed()->find((int) $matches[1]);
                if ($doc) {
                    $doc->forceDelete();
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->info("死记录清理：删除 {$deleted} 条。");
            Log::warning('文件巡检清理死记录', ['deleted' => $deleted]);
        }
    }

    private function cleanEmptyDirs(): void
    {
        foreach (self::MANAGED as $diskName => $directories) {
            $disk = Storage::disk($diskName);

            foreach ($directories as $directory) {
                if (! $disk->exists($directory)) {
                    continue;
                }

                $subDirectories = $disk->allDirectories($directory);
                usort($subDirectories, fn ($a, $b) => substr_count($b, '/') <=> substr_count($a, '/'));

                foreach ($subDirectories as $subDirectory) {
                    if ($disk->files($subDirectory) === [] && $disk->directories($subDirectory) === []) {
                        $disk->deleteDirectory($subDirectory);
                    }
                }
            }
        }
    }

    private function ageInMinutes($disk, string $path): ?int
    {
        try {
            $modified = $disk->lastModified($path);

            return max(0, (int) floor((time() - $modified) / 60));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
