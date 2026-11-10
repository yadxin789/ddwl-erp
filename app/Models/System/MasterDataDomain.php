<?php

namespace App\Models\System;

use App\Models\Organization\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 基础数据归口台账：每一类基础数据只对应一个归口部门。
 * 归口部门负责口径，管理员负责系统维护。
 */
class MasterDataDomain extends Model
{
    protected $fillable = [
        'code', 'name', 'owner_department_id', 'route_name', 'description', 'status', 'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }
}
