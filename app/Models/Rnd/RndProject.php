<?php

namespace App\Models\Rnd;

use App\Models\Concerns\HasDataScope;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RndProject extends Model
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本项目的创建人（S5）；工程师的"本人项目"另由任务执行人关系判定 */
    protected static ?string $dataScopeOwnerColumn = 'creator_id';

    /** P1-05：阶段（立项→设计→试产→量产），试产需有基线图档 */
    public const STAGES = [
        'initiation' => '立项',
        'design' => '设计',
        'trial' => '试产',
        'mass_production' => '量产',
    ];

    /**
     * 项目状态四态（2026-11-12 收敛）：
     * 取消"逾期进行中/逾期完成"，逾期与否由「进度 + 计划日期」在页面判断，不再落成状态。
     */
    public const STATUSES = [
        'initiation' => '立项中',
        'in_progress' => '进行中',
        'paused' => '已暂停',
        'completed' => '已完成',
    ];

    /** 状态徽标配色（Bootstrap） */
    public const STATUS_BADGES = [
        'initiation' => 'secondary',
        'in_progress' => 'primary',
        'paused' => 'warning',
        'completed' => 'success',
    ];

    protected $fillable = ['project_code', 'project_name', 'product_name', 'product_model', 'version', 'creator_id', 'start_date', 'end_date', 'status', 'stage', 'stage_changed_at', 'progress'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'stage_changed_at' => 'datetime'];

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? (string) $this->stage;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'secondary';
    }

    /**
     * 是否逾期：未完成且计划结束日已过。
     * 项目状态里不再有"逾期"，逾期由这里结合进度实时判断（页面展示用）。
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'completed'
            && $this->end_date !== null
            && $this->end_date->lt(now()->startOfDay());
    }

    /** 逾期天数（未逾期返回 0） */
    public function overdueDays(): int
    {
        return $this->isOverdue() ? (int) $this->end_date->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    /** 项目参与人：创建人 + 任务执行人 + 任务审核人（去重） */
    public function participantIds(): array
    {
        $ids = collect([$this->creator_id])
            ->merge($this->tasks->pluck('executor_id'))
            ->merge($this->tasks->pluck('auditor_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }

    public function stageIndex(): int
    {
        $keys = array_keys(self::STAGES);

        return array_search($this->stage, $keys, true) === false ? 0 : (int) array_search($this->stage, $keys, true);
    }


    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(RndProjectTask::class, 'project_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RndProjectDocument::class, 'project_id');
    }

    /**
     * P0-05：路由绑定绕开行级范围（只按归属列会 404 掉"执行人但非创建人"的工程师），
     * 访问控制交给 RndProjectPolicy。见 D-01。
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    /**
     * "我的项目"统一口径：我创建的 + 我是任务执行人的 + 我是任务审核人的
     * （与参考项目 myProjects 的“创建 ∪ 执行 ∪ 审核”一致，WorkspaceController 共用）。
     */
    public function scopeMine($query, ?int $userId = null)
    {
        $userId = $userId ?: (int) auth()->id();

        return $query->withoutGlobalScope('data_scope')
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('tasks', fn ($t) => $t->where('executor_id', $userId))
                    ->orWhereHas('tasks', fn ($t) => $t->where('auditor_id', $userId));
            });
    }

    /** 按四态筛选；非法/空值不筛选 */
    public function scopeByStatus($query, ?string $status = null)
    {
        return ($status && array_key_exists($status, self::STATUSES))
            ? $query->where('status', $status)
            : $query;
    }

    /** 是否本项目参与人（创建人 / 任务执行人 / 任务审核人） */
    public function hasParticipant(int $userId): bool
    {
        return in_array($userId, $this->participantIds(), true);
    }
}
