<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'dept_code', 'dept_name', 'parent_id', 'manager_id', 'sort_order', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * 部门经理（任务审核人唯一来源）。
     *
     * 优先使用 departments.manager_id；历史/演示数据未维护时，按岗位数据范围与岗位名称
     * 识别“本部门负责人”（全部数据范围 + 岗位名含经理/主管），仍找不到则取本部门
     * 第一个数据范围为 all 的在岗账号。没有经理的部门返回 null（该部门任务不能派审核人）。
     */
    public function resolvedManager(): ?User
    {
        if ($this->manager_id) {
            $manager = $this->manager;
            if ($manager && (int) $manager->status === 1) {
                return $manager;
            }
        }

        $base = User::query()
            ->where('department_id', $this->id)
            ->where('status', 1)
            ->whereHas('position', fn ($q) => $q->where('data_scope_type', 'all'));

        $named = (clone $base)
            ->whereHas('position', function ($q) {
                $q->where(function ($inner) {
                    $inner->where('pos_name', 'like', '%经理%')
                        ->orWhere('pos_name', 'like', '%主管%');
                });
            })
            ->orderBy('id')
            ->first();

        return $named ?: $base->orderBy('id')->first();
    }
}
