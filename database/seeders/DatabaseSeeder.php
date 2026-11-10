<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * P0 初始化：角色+权限+部门+仓库+编号规则+管理员+演示用户+演示数据
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            // v1.3：部门 10 个 + 岗位 29 个（含岗位→角色绑定），由 config/navigation.php 驱动
            PositionSeeder::class,
            WarehouseSeeder::class,
            NumberSequenceSeeder::class,
            AdminSeeder::class,
            DemoUserSeeder::class,
            // v1.3：29 岗位角色 + 4 兼容角色 + 演示账号岗位绑定（必须在账号创建之后）
            NavigationRoleSeeder::class,
            DemoDataSeeder::class,
            // P1-04 端到端验收剧本（客户/供应商/物料/BOM/销售单/采购单/工单，测试与演示共用）
            AcceptanceSeeder::class,
            ApsSeeder::class,
            // P1-02 A 类物料虚拟标准价（依赖 Acceptance/DemoData 的库存）
            DemoStandardCostSeeder::class,
            // P1-01 六条业务审批流（依赖演示账号 id）
            ApprovalFlowSeeder::class,
            // v1.3 S6b：项目模板预置（2 个可点样例）
            ProjectTemplateSeeder::class,
        ]);
    }
}
