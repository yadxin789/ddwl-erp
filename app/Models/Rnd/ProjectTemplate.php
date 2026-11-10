<?php

namespace App\Models\Rnd;

use Illuminate\Database\Eloquent\Model;

/**
 * 项目模板：复用既有表 project_templates（code / name / steps）。
 * steps 存任务类型 ID 数组，用于一键建项目并按模板生成任务。
 */
class ProjectTemplate extends Model
{
    protected $fillable = ['code', 'name', 'steps'];

    protected $casts = ['steps' => 'array'];

    public function taskTypes()
    {
        $ids = array_filter((array) ($this->steps ?? []), 'is_numeric');

        return RndTaskType::whereIn('id', $ids)->orderBy('sort_order')->get();
    }
}
