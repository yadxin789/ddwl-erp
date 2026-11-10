<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated HR 部门视图与 Organization\Department 概念重叠。
 *             新代码请使用 App\Models\Organization\Department（表 departments）。
 *             本 Model 暂保留以兼容 hr_departments 表的历史数据，后续再做数据合并。
 */
class HrDepartment extends Model
{
    protected $fillable = ['code', 'name', 'parent_id', 'manager'];
}
