<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\Organization\Position;
use App\Models\Organization\User;
use Illuminate\Database\Seeder;

/**
 * 演示用户 Seeder：为每个岗位、每种角色各创建一个演示用户
 *
 * 账号规则：username 与 name 都使用个人姓名（张三、李四、王五……），
 * 岗位只通过 position_id / 角色体现，不再把岗位名当成人名。
 * 密码统一 123456；email 保留为演示邮箱，仅用于登录/通知。
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // 角色级演示用户（对应 RoleSeeder 各角色）
        $roleUsers = [
            ['username' => '李四', 'name' => '李四', 'email' => 'wh-supervisor@demo.com', 'role' => 'warehouse_supervisor', 'dept' => 'D-WH', 'pos' => '仓库主管'],
            ['username' => '王五', 'name' => '王五', 'email' => 'wh-keeper@demo.com', 'role' => 'warehouse_keeper', 'dept' => 'D-WH', 'pos' => '仓管'],
            ['username' => '赵六', 'name' => '赵六', 'email' => 'purchase@demo.com', 'role' => 'purchase_specialist', 'dept' => 'D-PUR', 'pos' => '采购专员'],
            ['username' => '钱七', 'name' => '钱七', 'email' => 'sales@demo.com', 'role' => 'sales_specialist', 'dept' => 'D-SAL', 'pos' => '销售专员'],
            ['username' => '孙八', 'name' => '孙八', 'email' => 'engineer@demo.com', 'role' => 'engineer', 'dept' => 'D-RND', 'pos' => '硬件工程师'],
            ['username' => '周九', 'name' => '周九', 'email' => 'quality@demo.com', 'role' => 'quality_supervisor', 'dept' => 'D-QC', 'pos' => '品质主管'],
        ];

        // 岗位级演示用户（未绑定角色，验证岗位维度的基础权限）
        $positionUsers = [
            ['username' => '吴十', 'name' => '吴十', 'email' => 'prod-supervisor@demo.com', 'dept' => 'D-PROD', 'pos' => '生产主管'],
            ['username' => '郑一', 'name' => '郑一', 'email' => 'iqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'IQC检验员'],
            ['username' => '王明', 'name' => '王明', 'email' => 'rnd-manager@demo.com', 'dept' => 'D-RND', 'pos' => '研发经理'],
            ['username' => '李娜', 'name' => '李娜', 'email' => 'admin-assistant@demo.com', 'dept' => 'D-ADM', 'pos' => '人事专员'],
            // v1.3 财务独立部门后的两个演示账号：出纳五页与会计八页的验收入口
            ['username' => '陈静', 'name' => '陈静', 'email' => 'accountant@demo.com', 'dept' => 'D-FIN', 'pos' => '会计'],
            ['username' => '刘芳', 'name' => '刘芳', 'email' => 'cashier@demo.com', 'dept' => 'D-FIN', 'pos' => '出纳'],
            // 老板只读驾驶舱的演示账号
            ['username' => '高远', 'name' => '高远', 'email' => 'gm@demo.com', 'dept' => 'D-GM', 'pos' => '总经理（老板）'],
            // v1.3 S8 补齐：29 岗位逐一有账号，矩阵才能穷举
            ['username' => '冯军', 'name' => '冯军', 'email' => 'prod-leader@demo.com', 'dept' => 'D-PROD', 'pos' => '生产组长'],
            ['username' => '蒋鹏', 'name' => '蒋鹏', 'email' => 'operator@demo.com', 'dept' => 'D-PROD', 'pos' => '作业员'],
            ['username' => '邓雪', 'name' => '邓雪', 'email' => 'ipqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'IPQC检验员'],
            ['username' => '曹颖', 'name' => '曹颖', 'email' => 'fqc-inspector@demo.com', 'dept' => 'D-QC', 'pos' => 'FQC检验员'],
            ['username' => '徐涛', 'name' => '徐涛', 'email' => 'pur-manager@demo.com', 'dept' => 'D-PUR', 'pos' => '采购主管'],
            ['username' => '罗娟', 'name' => '罗娟', 'email' => 'sal-manager@demo.com', 'dept' => 'D-SAL', 'pos' => '销售主管'],
            ['username' => '彭欣', 'name' => '彭欣', 'email' => 'sal-cs@demo.com', 'dept' => 'D-SAL', 'pos' => '客服'],
            ['username' => '唐斌', 'name' => '唐斌', 'email' => 'eng-sw@demo.com', 'dept' => 'D-RND', 'pos' => '软件工程师'],
            ['username' => '韩磊', 'name' => '韩磊', 'email' => 'eng-me@demo.com', 'dept' => 'D-RND', 'pos' => '结构工程师'],
            ['username' => '谢婷', 'name' => '谢婷', 'email' => 'pkg-designer@demo.com', 'dept' => 'D-RND', 'pos' => '包装设计师'],
            ['username' => '许凯', 'name' => '许凯', 'email' => 'id-designer@demo.com', 'dept' => 'D-RND', 'pos' => '外观设计师'],
            ['username' => '卢伟', 'name' => '卢伟', 'email' => 'wh-receiver@demo.com', 'dept' => 'D-WH', 'pos' => '收货员'],
            ['username' => '苏琴', 'name' => '苏琴', 'email' => 'adm-manager@demo.com', 'dept' => 'D-ADM', 'pos' => '行政经理'],
            ['username' => '魏晨', 'name' => '魏晨', 'email' => 'ecom-operator@demo.com', 'dept' => 'D-ECM', 'pos' => '电商运营'],
            ['username' => '叶萌', 'name' => '叶萌', 'email' => 'ecom-cs@demo.com', 'dept' => 'D-ECM', 'pos' => '电商客服'],
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
