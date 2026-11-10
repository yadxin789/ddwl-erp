<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 项目模板预置（S6b 遗留补数据）：只为让"创建项目模板 / 一键建项目"页有可点样例。
 * steps 存 task_types.id 数组，与 ProjectTemplateController 的读取口径一致。
 */
class ProjectTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'TPL-HW-NPI',
                'name' => '硬件新品开发（标准流程）',
                'tasks' => [
                    ['name' => '市场调研报告', 'type' => 3],
                    ['name' => '产品需求规格书(PRD)', 'type' => 4],
                    ['name' => '电路原理图', 'type' => 1],
                    ['name' => 'PCB Layout图', 'type' => 7],
                    ['name' => 'BOM物料清单', 'type' => 8],
                    ['name' => '结构设计图', 'type' => 13],
                    ['name' => '样机测试报告', 'type' => 25],
                    ['name' => '安规认证报告', 'type' => 27],
                    ['name' => '项目总结报告', 'type' => 38],
                ],
            ],
            [
                'code' => 'TPL-PKG-REV',
                'name' => '包装改版（含外观确认）',
                'tasks' => [
                    ['name' => '市场调研报告', 'type' => 3],
                    ['name' => '包装设计图', 'type' => 19],
                    ['name' => '样机测试报告', 'type' => 25],
                    ['name' => '用户手册', 'type' => 35],
                    ['name' => '项目总结报告', 'type' => 38],
                ],
            ],
        ];

        $typeIds = DB::table('rnd_task_types')->pluck('id', 'name');

        foreach ($templates as $template) {
            $steps = [];
            foreach ($template['tasks'] as $task) {
                if (isset($typeIds[$task['name']])) {
                    $steps[] = (int) $typeIds[$task['name']];
                }
            }

            $templateId = DB::table('project_templates')->where('code', $template['code'])->value('id');

            if (! $templateId) {
                $templateId = DB::table('project_templates')->insertGetId([
                    'code' => $template['code'],
                    'name' => $template['name'],
                    'description' => '系统预置样例模板',
                    'steps' => json_encode($steps),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('project_templates')->where('id', $templateId)->update([
                    'steps' => json_encode($steps),
                    'updated_at' => now(),
                ]);
            }

            // template_tasks 只做展示用，按 template_id 幂等补齐
            if (DB::table('template_tasks')->where('template_id', $templateId)->count() === 0) {
                $rows = [];
                foreach ($template['tasks'] as $index => $task) {
                    $rows[] = [
                        'template_id' => $templateId,
                        'name' => $task['name'],
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('template_tasks')->insert($rows);
            }
        }
    }
}
