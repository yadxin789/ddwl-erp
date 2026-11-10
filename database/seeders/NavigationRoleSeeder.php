<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 岗位角色 Seeder —— 对齐《岗位-菜单-页面规划-v1.3》§3 / §7.2
 *
 * 只新增 29 个岗位角色 + 4 个旧名兼容角色，不动历史 7 个角色（迁移期并存）；
 * 权限集合逐岗位来自 §7.2，符合"每岗位至少绑定 1 个角色"。
 */
class NavigationRoleSeeder extends Seeder
{
    /** 演示账号 → 新岗位角色（含 4 个原本无角色的账号） */
    private array $demoBindings = [
        'admin' => 'role_sys_admin',
        'wh-supervisor' => 'role_wh_supervisor',
        'wh-keeper' => 'role_wh_keeper',
        'purchase' => 'role_pur_specialist',
        'sales' => 'role_sal_specialist',
        'engineer' => 'role_eng_hw',
        'quality' => 'role_qc_manager',
        'prod-supervisor' => 'role_prod_manager',
        'iqc-inspector' => 'role_qc_iqc',
        'rnd-manager' => 'role_rnd_manager',
        'admin-assistant' => 'role_hr_specialist',
        'accountant' => 'role_fin_accountant',
        'cashier' => 'role_fin_cashier',
        'gm' => 'role_gm',
        // v1.3 S8：29 岗位逐一绑定
        'prod-leader' => 'role_prod_leader',
        'operator' => 'role_prod_operator',
        'ipqc-inspector' => 'role_qc_ipqc',
        'fqc-inspector' => 'role_qc_fqc',
        'pur-manager' => 'role_pur_manager',
        'sal-manager' => 'role_sal_manager',
        'sal-cs' => 'role_sal_cs',
        'eng-sw' => 'role_eng_sw',
        'eng-me' => 'role_eng_me',
        'pkg-designer' => 'role_pkg_designer',
        'id-designer' => 'role_id_designer',
        'wh-receiver' => 'role_wh_receiver',
        'adm-manager' => 'role_adm_manager',
        'ecom-operator' => 'role_ecom_operator',
        'ecom-cs' => 'role_ecom_cs',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $existing = Permission::pluck('name')->all();

        $map = [
            'role_sys_admin' => ['admin.department.manage', 'admin.permission.manage', 'admin.position.manage', 'ai.guide.manage', 'ai.provider.manage', 'system.devops', 'system.event', 'system.platform', 'system.role.create', 'system.role.edit', 'system.role.view', 'system.security.view', 'system.sync', 'system.tenant', 'system.user.create', 'system.user.delete', 'system.user.edit', 'system.user.view', 'system.wecom'],   // 系统管理员
            'role_prod_manager' => ['inventory.stocktake', 'inventory.view', 'material.view', 'production.complete', 'production.create', 'production.issue.approve', 'production.issue.request', 'production.order.view', 'production.release', 'production.report', 'production.view', 'purchase.view', 'quality.view'],   // 生产主管
            'role_prod_leader' => ['inventory.adjust.request', 'inventory.stocktake', 'inventory.view', 'material.view', 'production.issue.request', 'production.order.view', 'production.report', 'production.view'],   // 生产组长（可发起盘点调整，审批归仓库主管）
            'role_prod_operator' => ['production.order.view', 'production.report', 'production.view'],   // 作业员
            'role_qc_manager' => ['bom.view', 'inventory.transaction.view', 'inventory.view', 'material.view', 'purchase.view', 'quality.approve', 'quality.defect.manage', 'quality.operate', 'quality.receipt.inspect', 'quality.template.manage', 'quality.view', 'report.quality'],   // 品质主管（含来料判定复核、检验结论审批）
            'role_qc_iqc' => ['material.view', 'purchase.view', 'quality.operate', 'quality.receipt.inspect', 'quality.view'],   // IQC 检验员（来料判定主责）
            'role_qc_ipqc' => ['material.view', 'production.order.view', 'production.view', 'quality.operate', 'quality.view'],   // IPQC 检验员（在制工单巡检需工单列表）
            'role_qc_fqc' => ['material.view', 'quality.operate', 'quality.view', 'sales.view'],   // FQC 检验员
            'role_pur_manager' => ['inventory.view', 'material.view', 'procurement.view', 'purchase.confirm', 'purchase.create', 'purchase.order.view', 'purchase.view', 'report.supplier', 'supplier.create', 'supplier.edit', 'supplier.view'],   // 采购主管（订单确认/关闭主审）
            'role_pur_specialist' => ['bom.view', 'inventory.view', 'material.view', 'procurement.operate', 'procurement.view', 'purchase.create', 'purchase.order.view', 'purchase.view', 'supplier.create', 'supplier.edit', 'supplier.view'],   // 采购专员
            'role_sal_manager' => ['customer.view', 'inventory.view', 'report.sales', 'sales.confirm', 'sales.create', 'sales.return.approve', 'sales.view'],   // 销售主管
            'role_sal_specialist' => ['crm.operate', 'crm.view', 'customer.create', 'customer.edit', 'customer.view', 'inventory.view', 'material.view', 'report.sales', 'sales.create', 'sales.view'],   // 销售专员（P0-08 报表按域授权）
            'role_sal_cs' => ['crm.operate', 'crm.view', 'customer.view', 'marketing.operate', 'marketing.view', 'material.view', 'sales.create', 'sales.view'],   // 客服（售后工单只看不改）
            'role_rnd_manager' => ['bom.approve', 'bom.view', 'file.resource.manage', 'material.create', 'material.edit', 'material.view', 'production.view', 'project.template.manage', 'rnd.approve', 'rnd.create', 'rnd.extension.approve', 'rnd.view'],   // 研发经理
            'role_eng_hw' => ['bom.import', 'bom.view', 'inventory.view', 'material.create', 'material.edit', 'material.view', 'production.create', 'production.view', 'rnd.create', 'rnd.view'],   // 硬件工程师
            'role_eng_sw' => ['bom.view', 'material.view', 'production.view', 'rnd.create', 'rnd.view'],   // 软件工程师
            'role_eng_me' => ['bom.import', 'bom.view', 'inventory.view', 'material.create', 'material.edit', 'material.view', 'production.view', 'rnd.create', 'rnd.view'],   // 结构工程师
            'role_pkg_designer' => ['bom.import', 'bom.view', 'design.file.manage', 'material.create', 'material.edit', 'material.view', 'rnd.create', 'rnd.view'],   // 包装设计师
            'role_id_designer' => ['bom.view', 'design.file.manage', 'material.create', 'material.edit', 'material.view', 'rnd.create', 'rnd.view'],   // 外观设计师
            'role_wh_supervisor' => ['bom.view', 'inventory.adjust', 'inventory.adjust.approve', 'inventory.adjust.request', 'inventory.operate', 'inventory.stocktake', 'inventory.transaction.view', 'inventory.view', 'material.view', 'purchase.receive', 'quality.view', 'sales.ship', 'warehouse.create', 'warehouse.edit', 'warehouse.view'],   // 仓库主管（库存调整审批人，P0-04）
            'role_wh_keeper' => ['inventory.adjust', 'inventory.adjust.request', 'inventory.operate', 'inventory.stocktake', 'inventory.transaction.view', 'inventory.view', 'material.view', 'production.issue', 'purchase.receive', 'quality.view', 'sales.ship', 'warehouse.view'],   // 仓管（只能发起调整，不能审批）
            'role_wh_receiver' => ['inventory.view', 'material.view', 'purchase.receive', 'quality.view'],   // 收货员
            'role_adm_manager' => ['announcement.manage', 'hr.operate', 'hr.position.manage', 'hr.view', 'system.voice'],   // 行政经理
            'role_hr_specialist' => ['hr.operate', 'hr.view'],   // 人事专员
            'role_fin_accountant' => ['cost.standard.manage', 'cost.standard.view', 'finance.arap.view', 'finance.bill.manage', 'finance.invoice.approve', 'finance.invoice.manage', 'finance.payment.approve', 'finance.payment.manage', 'finance.payment.view', 'finance.reconcile.manage', 'finance.salary.manage', 'report.finance'],   // 会计（P1-02 标准成本维护）
            'role_fin_cashier' => ['finance.arap.view', 'finance.bank.reconcile', 'finance.cashbook.manage', 'finance.note.manage', 'finance.payment.execute', 'finance.payment.view'],   // 出纳（不得开票：D-15 财务分离，已收回 finance.invoice.manage）
            'role_ecom_operator' => ['ecom.operate', 'ecom.view', 'inventory.view', 'marketing.operate', 'marketing.view', 'material.view', 'procurement.operate', 'procurement.view', 'product.operate', 'product.view'],   // 电商运营
            'role_ecom_cs' => ['ecom.view', 'marketing.operate', 'marketing.view', 'product.view'],   // 电商客服（售后工单只看不改）
            'role_gm' => ['cost.standard.view', 'dashboard.view', 'finance.arap.view', 'finance.invoice.approve', 'finance.payment.approve', 'purchase.confirm', 'report.finance', 'report.quality', 'report.sales', 'report.supplier', 'sales.return.approve'],   // 总经理（老板）：兼采购确认代批（D-21）
            'role_project_creator' => ['project.template.manage', 'rnd.view'],   // 项目创建人（兼容）
            'role_task_executor' => ['rnd.create', 'rnd.view'],   // 项目任务执行人（兼容）
            'role_task_auditor' => ['rnd.approve', 'rnd.view'],   // 项目任务审核人（兼容）
            'role_support_staff' => ['support.view'],   // 其它非项目参与人员（兼容）
        ];

        foreach ($map as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $granted = array_values(array_intersect($permissions, $existing));
            $missing = array_values(array_diff($permissions, $existing));
            if ($missing) {
                $this->command?->warn(sprintf('[%s] 权限库缺失 %d 条：%s', $roleName, count($missing), implode(', ', $missing)));
            }
            $role->syncPermissions($granted);
        }

        // 共享菜单「AI 助手」按 §5.2 是全员共享项，其路由守卫要求 ai.view；
        // 因此所有角色统一授予该权限（不含任何业务数据权限）。
        if (in_array('ai.view', $existing, true)) {
            foreach (Role::all() as $role) {
                if (! $role->hasPermissionTo('ai.view')) {
                    $role->givePermissionTo('ai.view');
                }
            }
        }

        // 铁律：管理员只管系统运维与后台管理，不参与业务（v1.3 §2.2 / D-01）
        // 历史 system_admin 曾领取全部 104 条权限，这里收敛为"仅运维 + 后台管理"白名单。
        $systemWhitelist = [
            'system.user.view', 'system.user.create', 'system.user.edit', 'system.user.delete',
            'system.role.view', 'system.role.create', 'system.role.edit', 'system.role.delete',
            'system.department.view', 'system.department.create', 'system.department.edit',
            'system.position.view', 'system.position.create', 'system.position.edit',
            'system.setting.view', 'system.setting.edit', 'system.audit.view',
            'system.devops', 'system.tenant', 'system.wecom', 'system.sync', 'system.platform',
            'system.event', 'system.security.view', 'system.approval.view', 'system.voice',
            'ai.view', 'ai.provider.manage', 'ai.guide.manage',
            'admin.department.manage', 'admin.position.manage', 'admin.permission.manage',
        ];
        $systemGranted = array_values(array_intersect($systemWhitelist, $existing));

        foreach (['role_sys_admin', 'system_admin'] as $sysRole) {
            $role = Role::where('name', $sysRole)->first();
            if ($role) {
                $role->syncPermissions($systemGranted);
            }
        }

        // 旧角色 7 个按 D-12 只读并存到 2027-02-14，但"跨岗位后门"必须立刻收回：
        // 历史 warehouse_keeper 含 sales.view，双角色账号（warehouse_keeper + role_wh_keeper）
        // 会绕过岗位守卫直接进销售订单页。G1 发货作业台上线后收回该权限。
        $legacyRevoke = [
            'warehouse_keeper' => ['sales.view', 'purchase.view', 'production.view'],
            'warehouse_supervisor' => ['sales.view', 'purchase.view', 'production.view'],
        ];
        foreach ($legacyRevoke as $legacyRole => $permissions) {
            $role = Role::where('name', $legacyRole)->first();
            if (! $role) {
                continue;
            }
            foreach ($permissions as $permission) {
                if (Permission::where('name', $permission)->exists()) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        // 演示账号绑定：兼容角色以外的账号补齐角色（修复 4 个账号 roles=NONE）
        foreach ($this->demoBindings as $username => $roleName) {
            $user = \App\Models\Organization\User::where('username', $username)->first();
            if (! $user) {
                $this->command?->warn('演示账号不存在：' . $username);
                continue;
            }
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        }
    }
}
