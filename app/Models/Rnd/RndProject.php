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

    protected $fillable = ['project_code', 'project_name', 'product_model', 'creator_id', 'start_date', 'end_date', 'status', 'stage', 'stage_changed_at', 'progress'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'stage_changed_at' => 'datetime'];

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? (string) $this->stage;
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
     * "我的项目"统一口径：我创建的 + 我是任务执行人的（与 WorkspaceController::myProjects 一致）
     */
    public function scopeMine($query, ?int $userId = null)
    {
        $userId = $userId ?: (int) auth()->id();

        return $query->withoutGlobalScope('data_scope')
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                    ->orWhereHas('tasks', fn ($t) => $t->where('executor_id', $userId));
            });
    }
}
