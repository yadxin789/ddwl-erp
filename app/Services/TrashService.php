<?php

namespace App\Services;

use App\Models\Office\ChatMessage;
use App\Models\Rnd\RndCollabDocument;
use App\Models\Rnd\RndProjectDocument;
use App\Models\System\MediaFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 回收站统一服务。
 *
 * 三类内容共用一套生命周期：
 *   软删除（保留物理文件，可恢复） → 回收站手动彻底删除 / 到期自动清理（删除物理文件）
 *
 * 个人回收站范围 = 我删除的 OR 我创建/上传的；管理员可查看全系统。
 */
class TrashService
{
    /** 可进入回收站的类型。物理文件字段只在这里维护一次。 */
    public const TYPES = [
        'chat_messages' => [
            'model' => ChatMessage::class,
            'label' => '聊天记录',
            'icon' => 'fa-comments',
            'display' => 'content',
            'owner' => 'sender_id',
            'owner_relation' => 'sender',
            'disk' => null,
            'path' => null,
        ],
        'collab_documents' => [
            'model' => RndCollabDocument::class,
            'label' => '协同文档',
            'icon' => 'fa-file-word',
            'display' => 'title',
            'owner' => 'creator_id',
            'owner_relation' => 'creator',
            'disk' => 'file_repository',
            'path' => 'storage_path',
        ],
        'media_files' => [
            'model' => MediaFile::class,
            'label' => '文件 / 图片',
            'icon' => 'fa-paperclip',
            'display' => 'file_name',
            'owner' => 'uploader_id',
            'owner_relation' => 'uploader',
            'disk' => 'local',
            'path' => 'file_path',
        ],
    ];

    public static function config(string $type): array
    {
        if (! isset(self::TYPES[$type])) {
            throw new RuntimeException('未知的回收站类型：' . $type);
        }

        return self::TYPES[$type];
    }

    /** @return Builder<Model> */
    public static function query(string $type): Builder
    {
        $config = self::config($type);
        /** @var class-string<Model> $model */
        $model = $config['model'];

        return $model::onlyTrashed();
    }

    /** 个人范围：删除人是我，或内容归属人是我。 */
    public static function scopeForUser(Builder $query, string $type, int $userId): Builder
    {
        $config = self::config($type);
        $owner = $config['owner'];

        return $query->where(function (Builder $q) use ($userId, $owner) {
            $q->where('deleted_by', $userId);
            if ($owner) {
                $q->orWhere($owner, $userId);
            }
        });
    }

    public static function isVisibleToUser(Model $item, string $type, int $userId): bool
    {
        $config = self::config($type);
        $owner = $config['owner'];

        return (int) $item->getAttribute('deleted_by') === $userId
            || ($owner && (int) $item->getAttribute($owner) === $userId);
    }

    public static function count(string $type, ?int $userId = null): int
    {
        $query = self::query($type);

        if ($userId !== null) {
            self::scopeForUser($query, $type, $userId);
        }

        return $query->count();
    }

    public static function label(string $type): string
    {
        return self::config($type)['label'];
    }

    public static function icon(string $type): string
    {
        return self::config($type)['icon'];
    }

    public static function displayName(string $type, Model $item): string
    {
        $value = trim((string) $item->getAttribute(self::config($type)['display']));

        if ($type === 'chat_messages') {
            $fileName = null;
            if ($item->getAttribute('file_id')) {
                $fileName = MediaFile::withTrashed()->whereKey($item->getAttribute('file_id'))->value('file_name');
            }

            $prefix = $value !== '' ? Str::limit($value, 60) : '聊天消息';
            return $fileName ? $prefix . ' [附件] ' . $fileName : $prefix;
        }

        return $value !== '' ? Str::limit($value, 80) : '未命名内容';
    }

    public static function ownerName(string $type, Model $item): string
    {
        $relation = self::config($type)['owner_relation'];
        $owner = $relation ? $item->{$relation} : null;

        return $owner?->name ?: '未知用户';
    }

    public static function size(string $type, Model $item): int
    {
        if ($type === 'media_files') {
            return (int) $item->getAttribute('file_size');
        }

        if ($type === 'collab_documents') {
            return (int) $item->getAttribute('size');
        }

        if ($type === 'chat_messages' && $item->getAttribute('file_id')) {
            return (int) MediaFile::withTrashed()
                ->whereKey($item->getAttribute('file_id'))
                ->value('file_size');
        }

        return 0;
    }

    public static function restore(string $type, Model $item): void
    {
        $item->restore();

        // 聊天消息恢复时，连带恢复同一条消息的附件记录。
        if ($type === 'chat_messages' && $item->getAttribute('file_id')) {
            $file = MediaFile::withTrashed()->whereKey($item->getAttribute('file_id'))->first();
            if ($file && $file->trashed()) {
                $file->restore();
            }
        }
    }

    /**
     * 彻底删除：先清物理文件，再删除回收站记录。
     * 与到期清理共用同一入口。
     */
    public static function forceDeleteDeep(string $type, Model $item): void
    {
        if ($type === 'chat_messages') {
            if ($item->getAttribute('file_id')) {
                $file = MediaFile::withTrashed()->whereKey($item->getAttribute('file_id'))->first();
                if ($file) {
                    self::purgeMediaFile($file, (int) $item->getKey());
                }
            }

            $item->forceDelete();
            return;
        }

        if ($type === 'collab_documents') {
            $path = (string) $item->getAttribute('storage_path');
            if ($path !== '' && Storage::disk('file_repository')->exists($path)) {
                Storage::disk('file_repository')->delete($path);
            }

            // 协同文档的历史版本快照（如果存在），一并清理。
            $historyDir = 'collab/' . $item->getAttribute('project_id') . '/history';
            $prefix = 'doc_' . $item->getKey() . '_v';
            foreach (Storage::disk('file_repository')->files($historyDir) as $history) {
                if (str_starts_with(basename($history), $prefix)) {
                    Storage::disk('file_repository')->delete($history);
                }
            }

            $item->forceDelete();
            return;
        }

        if ($type === 'media_files') {
            /** @var MediaFile $item */
            self::purgeMediaFile($item, null);
            return;
        }

        throw new RuntimeException('未知的回收站类型：' . $type);
    }

    /**
     * 删除文件记录和物理文件；仍被聊天消息或研发交付物引用时拒绝删除。
     */
    private static function purgeMediaFile(MediaFile $file, ?int $exceptChatMessageId): void
    {
        $references = ChatMessage::withTrashed()
            ->where('file_id', $file->getKey())
            ->when($exceptChatMessageId, fn (Builder $q) => $q->where('id', '!=', $exceptChatMessageId))
            ->count();

        if (RndProjectDocument::query()->where('file_id', $file->getKey())->exists()) {
            $references++;
        }

        if ($references > 0) {
            throw new RuntimeException(
                '文件仍被业务数据引用，已跳过彻底删除：' . $file->file_name
            );
        }

        $path = (string) $file->getAttribute('file_path');
        foreach (['local', 'public'] as $disk) {
            if ($path !== '' && Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }

        $file->forceDelete();
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }

    public static function remainingDays(?Model $item, int $retentionDays): int
    {
        $deletedAt = $item?->getAttribute('deleted_at');
        if (! $deletedAt) {
            return 0;
        }

        return max(0, $retentionDays - $deletedAt->diffInDays(now()));
    }
}
