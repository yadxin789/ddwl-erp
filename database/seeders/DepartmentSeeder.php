<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\Organization\Position;
use Illuminate\Database\Seeder;

/**
 * ⚠️ 已由 PositionSeeder 取代（v1.3）：部门 11 个 + 岗位 29 个 + 岗位→角色绑定
 * 统一由 config/navigation.php 驱动。本文件保留仅为兼容历史命令，不要再加入 DatabaseSeeder。
 *
 * 部门 Seeder：生产部/品质部/采购部/销售部/研发部/仓储部/行政部
 * 每个部门创建核心岗位
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['dept_code' => 'D-PROD', 'dept_name' => '生产部', 'sort_order' => 1, 'positions' => ['生产主管', '生产组长', '作业员']],
            ['dept_code' => 'D-QC', 'dept_name' => '品质部', 'sort_order' => 2, 'positions' => ['品质主管', 'IQC检验员', 'IPQC检验员', 'FQC检验员']],
            ['dept_code' => 'D-PUR', 'dept_name' => '采购部', 'sort_order' => 3, 'positions' => ['采购主管', '采购专员']],
            ['dept_code' => 'D-SAL', 'dept_name' => '销售部', 'sort_order' => 4, 'positions' => ['销售主管', '销售专员', '客服']],
            ['dept_code' => 'D-RND', 'dept_name' => '研发部', 'sort_order' => 5, 'positions' => ['研发经理', '硬件工程师', '软件工程师', '结构工程师']],
            ['dept_code' => 'D-WH', 'dept_name' => '仓储部', 'sort_order' => 6, 'positions' => ['仓库主管', '仓管', '收货员']],
            ['dept_code' => 'D-ADM', 'dept_name' => '行政部', 'sort_order' => 7, 'positions' => ['行政经理', '人事专员', '财务']],
        ];

        foreach ($departments as $dept) {
            $positions = $dept['positions'];
            unset($dept['positions']);

            $department = Department::firstOrCreate(
                ['dept_code' => $dept['dept_code']],
                $dept
            );

            $seq = 1;
            foreach ($positions as $posName) {
                Position::firstOrCreate(
                    ['pos_code' => $dept['dept_code'] . '-P' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT)],
                    [
                        'pos_name' => $posName,
                        'department_id' => $department->id,
                        'status' => 1,
                    ]
                );
                $seq++;
            }
        }
    }
}
