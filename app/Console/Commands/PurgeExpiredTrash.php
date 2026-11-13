<?php

namespace App\Console\Commands;

use App\Services\TrashService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 回收站到期自动清理。
 *
 * 保留期限由 config('trash.retention_days') 控制，默认 15 天。
 * 与管理员手动「彻底删除」共用 TrashService::forceDeleteDeep()，规则完全一致。
 */
class PurgeExpiredTrash extends Command
{
    protected $signature = 'trash:purge-expired
                            {--days= : 覆盖配置中的保留天数}
                            {--type= : 只清理指定类型}
                            {--dry-run : 只列出将被清理的内容，不实际删除}';

    protected $description = '彻底删除回收站中超过保留期限的内容（含物理文件）';

    protected int $purgedTotal = 0;

    protected int $failedTotal = 0;

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('trash.retention_days', 15));
        $dryRun = (bool) $this->option('dry-run');
        $onlyType = $this->option('type');

        if ($days < 1) {
            $this->error('保留天数必须大于 0，已中止。');

            return Command::FAILURE;
        }

        if ($onlyType && ! array_key_exists($onlyType, TrashService::TYPES)) {
            $this->error('未知的回收站类型：' . $onlyType);

            return Command::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $prefix = $dryRun ? '[预演] ' : '';

        $this->info("{$prefix}保留期限：{$days} 天（清理 {$cutoff->toDateTimeString()} 之前删除的内容）");

        foreach (TrashService::TYPES as $type => $config) {
            if ($onlyType && $onlyType !== $type) {
                continue;
            }

            $before = $this->purgedTotal;
            $this->purgeType($type, $config['model'], $cutoff, $dryRun);
            $count = $this->purgedTotal - $before;

            if ($count > 0) {
                $this->line(sprintf('  %s%s：%d 条', $prefix, $config['label'], $count));
            }
        }

        if ($this->purgedTotal === 0) {
            $this->info("{$prefix}没有超过保留期限的内容，无需清理。");

            return $this->failedTotal > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $this->info(sprintf('%s共清理 %d 条。', $prefix, $this->purgedTotal));

        if (! $dryRun) {
            Log::info('回收站到期清理', [
                'days' => $days,
                'type' => $onlyType ?: 'all',
                'purged' => $this->purgedTotal,
                'failed' => $this->failedTotal,
            ]);
        }

        return $this->failedTotal > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** @param class-string<Model> $modelClass */
    protected function purgeType(string $type, string $modelClass, Carbon $cutoff, bool $dryRun): void
    {
        $modelClass::onlyTrashed()
            ->where('deleted_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($items) use ($type, $dryRun) {
                foreach ($items as $item) {
                    try {
                        if (! $dryRun) {
                            TrashService::forceDeleteDeep($type, $item);
                        }
                        $this->purgedTotal++;
                    } catch (\Throwable $e) {
                        $this->failedTotal++;
                        $this->error(sprintf('  ✗ %s#%s 清理失败：%s', $type, $item->getKey(), $e->getMessage()));
                        report($e);
                    }
                }
            });
    }
}
