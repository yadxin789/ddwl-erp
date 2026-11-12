<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProjectLog extends Model
{
    protected $fillable = ['project_id', 'task_id', 'user_id', 'action', 'detail', 'log_type'];

    protected $casts = ['created_at' => 'datetime'];

    public const TYPE_BADGES = [
        'operation' => 'primary',
        'status' => 'info',
        'stage' => 'warning',
        'document' => 'success',
        'extension' => 'danger',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(RndProjectTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function badge(): string
    {
        return self::TYPE_BADGES[$this->log_type] ?? 'secondary';
    }

    /**
     * 写一条履历。任何写动作失败都不应影响主流程，因此这里吞掉异常并记日志。
     */
    public static function record(int $projectId, string $action, ?string $detail = null, string $type = 'operation', ?int $taskId = null, ?int $userId = null): ?self
    {
        try {
            return static::create([
                'project_id' => $projectId,
                'task_id' => $taskId,
                'user_id' => $userId ?? (auth()->id() ?? null),
                'action' => mb_substr($action, 0, 64),
                'detail' => $detail ? mb_substr($detail, 0, 500) : null,
                'log_type' => $type,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('研发项目履历写入失败', ['project_id' => $projectId, 'action' => $action, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
