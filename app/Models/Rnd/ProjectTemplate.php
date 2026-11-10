<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 项目模板：复用既有表 project_templates（code / name / description / user_id / steps）。
 * steps 兼容两种格式：
 * - 旧格式：任务类型 ID 数组 [1, 2, 3]
 * - 新格式：任务配置数组 [{task_type_id, name, department_id, executor_id, auditor_id, days, sort_order}]
 */
class ProjectTemplate extends Model
{
    protected $fillable = ['code', 'name', 'description', 'user_id', 'steps'];

    protected $casts = ['steps' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** 取出模板任务配置，并把旧格式的 ID 数组归一化为配置数组 */
    public function taskConfigs(): array
    {
        return collect($this->steps ?? [])
            ->map(function ($step) {
                if (is_array($step)) {
                    return $step;
                }
                if (is_numeric($step)) {
                    return ['task_type_id' => (int) $step];
                }

                return null;
            })
            ->filter(fn ($step) => ! empty($step['task_type_id']))
            ->values()
            ->all();
    }

    public function taskTypes()
    {
        $ids = collect($this->taskConfigs())->pluck('task_type_id')->filter()->unique()->all();

        return RndTaskType::whereIn('id', $ids)->orderBy('sort_order')->get();
    }
}
