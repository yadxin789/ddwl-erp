<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\Organization\Position;
use App\Models\Organization\User;
use Illuminate\Database\Seeder;

/**
 * 演示用户 Seeder：为每个岗位、每种角色各创建一个演示用户
 * 账号命名规则：{角色/岗位英文slug}@demo.com，密码统一 123456
 * 跨模块同名角色加模块前缀（rnd- / ecom-）区分
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // 角色级演示用户（对应 RoleSeeder 各角色）
        $roleUsers = [
            ['username' => 'wh-supervisor', 'name' => '仓库主管', 'email' => 'wh-supervisor@demo.com', 'role' => 'warehouse_supervisor', 'dept' => 'D-WH', 'pos' => '仓库主管'],
            ['username' => 'wh-keeper', 'name' => '仓管', 'email' => 'wh-keeper@demo.com', 'role' => 'warehouse_keeper', 'dept' => 'D-WH', 'pos' => '仓管'],
            ['username' => 'purchase', 'name' => '采购专员', 'email' => 'purchase@demo.com', 'role' => 'purchase_specialist', 'dept' => 'D-PUR', 'pos' => '采购专员'],
            ['username' => 'sales', 'name' => '销售专员', 'email' => 'sales@demo.com', 'role' => 'sales_specialist', 'dept' => 'D-SAL', 'pos' => '销售专员'],
            ['username' => 'engineer', 'name' => '工程师', 'email' => 'engineer@demo.com', 'role' => 'engineer', 'dept' => 'D-RND', 'pos' => '硬件工程师'],
            ['username' => 'quality', 'name' => '品质主管', 'email' => 'quality@demo.com', 'role' => 'quality_supervisor', 'dept' => 'D-QC', 'pos' => '品质主管'],
        ];

        // 岗位级演示用户（未绑定角色，验证岗位维度的基础权限）
        $positionUsers = [
            ['username' => 'prod-supervisor', 'name' => '生产主管', 'email' => 'prod-supervisor@demo.com', 'dept' => 'D-PROD', 'pos' => '生产主管'],
            ['username' => 'iqc-inspector', 'name' => 'IQC检验员', 'email' => 'iqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'IQC检验员'],
            ['username' => 'rnd-manager', 'name' => '研发经理', 'email' => 'rnd-manager@demo.com', 'dept' => 'D-RND', 'pos' => '研发经理'],
            ['username' => 'admin-assistant', 'name' => '人事专员', 'email' => 'admin-assistant@demo.com', 'dept' => 'D-ADM', 'pos' => '人事专员'],
            // v1.3 财务独立部门后的两个演示账号：出纳五页与会计八页的验收入口
            ['username' => 'accountant', 'name' => '会计', 'email' => 'accountant@demo.com', 'dept' => 'D-FIN', 'pos' => '会计'],
            ['username' => 'cashier', 'name' => '出纳', 'email' => 'cashier@demo.com', 'dept' => 'D-FIN', 'pos' => '出纳'],
            // 老板只读驾驶舱的演示账号
            ['username' => 'gm', 'name' => '总经理', 'email' => 'gm@demo.com', 'dept' => 'D-GM', 'pos' => '总经理（老板）'],
            // v1.3 S8 补齐：29 岗位逐一有账号，矩阵才能穷举
            ['username' => 'prod-leader', 'name' => '生产组长', 'email' => 'prod-leader@demo.com', 'dept' => 'D-PROD', 'pos' => '生产组长'],
            ['username' => 'operator', 'name' => '作业员', 'email' => 'operator@demo.com', 'dept' => 'D-PROD', 'pos' => '作业员'],
            ['username' => 'ipqc-inspector', 'name' => 'IPQC检验员', 'email' => 'ipqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'IPQC检验员'],
            ['username' => 'fqc-inspector', 'name' => 'FQC检验员', 'email' => 'fqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'FQC检验员'],
            ['username' => 'pur-manager', 'name' => '采购主管', 'email' => 'pur-manager@demo.com', 'dept' => 'D-PUR', 'pos' => '采购主管'],
            ['username' => 'sal-manager', 'name' => '销售主管', 'email' => 'sal-manager@demo.com', 'dept' => 'D-SAL', 'pos' => '销售主管'],
            ['username' => 'sal-cs', 'name' => '客服', 'email' => 'sal-cs@demo.com', 'dept' => 'D-SAL', 'pos' => '客服'],
            ['username' => 'eng-sw', 'name' => '软件工程师', 'email' => 'eng-sw@demo.com', 'dept' => 'D-RND', 'pos' => '软件工程师'],
            ['username' => 'eng-me', 'name' => '结构工程师', 'email' => 'eng-me@demo.com', 'dept' => 'D-RND', 'pos' => '结构工程师'],
            ['username' => 'pkg-designer', 'name' => '包装设计师', 'email' => 'pkg-designer@demo.com', 'dept' => 'D-RND', 'pos' => '包装设计师'],
            ['username' => 'id-designer', 'name' => '外观设计师', 'email' => 'id-designer@demo.com', 'dept' => 'D-RND', 'pos' => '外观设计师'],
            ['username' => 'wh-receiver', 'name' => '收货员', 'email' => 'wh-receiver@demo.com', 'dept' => 'D-WH', 'pos' => '收货员'],
            ['username' => 'adm-manager', 'name' => '行政经理', 'email' => 'adm-manager@demo.com', 'dept' => 'D-ADM', 'pos' => '行政经理'],
            ['username' => 'ecom-operator', 'name' => '电商运营', 'email' => 'ecom-operator@demo.com', 'dept' => 'D-ECM', 'pos' => '电商运营'],
            ['username' => 'ecom-cs', 'name' => '电商客服', 'email' => 'ecom-cs@demo.com', 'dept' => 'D-ECM', 'pos' => '电商客服'],
        ];

        foreach (array_merge($roleUsers, $positionUsers) as $item) {
            $dept = Department::where('dept_code', $item['dept'])->first();
            $pos = $dept
                ? Position::where('department_id', $dept->id)->where('pos_name', $item['pos'])->first()
                : null;

            $user = User::firstOrCreate(
                ['username' => $item['username']],
                [
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'password' => '123456',
                    'department_id' => $dept?->id,
                    'position_id' => $pos?->id,
                    'status' => 1,
                ]
            );

            if (isset($item['role'])) {
                $user->syncRoles([$item['role']]);
            }
        }
    }
}
