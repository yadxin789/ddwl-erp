<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 权限 Seeder：按模块授权，权限命名规范 {module}.{action}
 * 模块：system/material/customer/supplier/warehouse/inventory/bom/purchase/sales/production/quality
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // 系统模块
            'system.user.view', 'system.user.create', 'system.user.edit', 'system.user.delete',
            'system.role.view', 'system.role.create', 'system.role.edit', 'system.role.delete',
            'system.department.view', 'system.department.create', 'system.department.edit',
            'system.position.view', 'system.position.create', 'system.position.edit',
            'system.setting.view', 'system.setting.edit',
            'system.audit.view',

            // 物料主数据（P2-03：material.view 拆为页面权限 material.list + 业务读取 material.read）
            'material.list', 'material.read',
            'material.create', 'material.edit', 'material.import',
            'material.category.view', 'material.category.edit',
            'material.mpn.view', 'material.mpn.create', 'material.mpn.edit',

            // 往来单位
            'customer.view', 'customer.create', 'customer.edit',
            'supplier.view', 'supplier.create', 'supplier.edit',

            // 仓库库存
            'warehouse.view', 'warehouse.create', 'warehouse.edit',
            'inventory.view', 'inventory.operate', 'inventory.adjust', 'inventory.audit',
            'inventory.adjust.request',        // 库存调整发起（仓管/组长，P0-04）
            'inventory.adjust.approve',        // 库存调整审批（仓库主管，P0-04）
            'inventory.transaction.view',

            // BOM
            'bom.view', 'bom.create', 'bom.edit', 'bom.approve', 'bom.import',

            // 采购
            'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.approve', 'purchase.confirm',
            'purchase.order.view',
            'purchase.receive', 'purchase.return',

            // 销售
            'sales.view', 'sales.create', 'sales.edit', 'sales.approve', 'sales.ship',
            'sales.return',

            // 生产
            'production.view', 'production.create', 'production.edit', 'production.release',
            'production.report', 'production.receive', 'production.order.view',

            // 质量（P2-03：quality.view 拆为三页权限；quality.operate 管写入）
            'quality.inspection.view', 'quality.template.view', 'quality.defect.view',
            'quality.create', 'quality.edit', 'quality.approve',

            // 报表（P0-08：按业务域拆分，财务数据不再对全员开放）
            'report.sales', 'report.quality', 'report.supplier', 'report.finance',

            // 系统扩展（研发/电商 P1 预留）
            'rnd.view', 'rnd.create', 'rnd.edit', 'rnd.approve',
            // 研发项目创建权与日常研发操作权分离：只有项目经理能建项目
            'rnd.project.create',
            'ecommerce.view', 'ecommerce.operate', 'ecommerce.audit',

            // P0 修复：补齐此前缺失的模块权限（审计报告 4.1 节）
            'system.devops', 'system.tenant', 'system.wecom',
            'system.sync', 'system.platform', 'system.event',
            'system.security.view', 'system.approval.view', 'system.voice',

            // AI
            'ai.view', 'ai.operate', 'ai.provider.manage',

            // 质量（查看权限已在上面按页拆分）
            'quality.operate',
            'quality.receipt.inspect',          // 来料检验判定（IQC 专属，收货岗不得判定）

            // CRM
            'crm.view', 'crm.operate',

            // 电商
            'ecom.view', 'ecom.operate',

            // 财务
            'finance.view', 'finance.operate',

            // HR
            'hr.view', 'hr.operate',

            // 营销
            'marketing.view', 'marketing.operate',

            // 商品中心
            'product.view', 'product.operate',

            // 采购计划
            'procurement.view', 'procurement.operate',

            // ── v1.3 岗位/菜单/页面规划 §7.3：新增权限（补齐"路由引用了但权限库没有"的硬缺陷） ──
            // 销售 / 生产
            'sales.confirm',                    // 订单确认（routes/web.php:134 引用，原先点击即 500）
            'production.complete',              // 完工入库
            'production.issue',                 // 领料出库
            'production.issue.request',         // 领料申请（G9）
            'production.issue.approve',         // 超 BOM 标准用量的领料审批（D-13）
            // 研发 / 项目
            'project.template.manage',          // 项目模板 CRUD（P-130）
            // 质量
            'quality.template.manage',          // 检验模板维护
            'quality.defect.manage',            // 缺陷代码维护
            // 仓储
            'inventory.stocktake',              // 盘点查看/录入
            // 财务（会计 / 出纳分离）
            'finance.invoice.manage',
            'finance.bill.manage',
            'finance.payment.manage',
            'finance.payment.execute',
            'finance.payment.view',
            'finance.cashbook.manage',
            'finance.bank.reconcile',
            'finance.arap.view',
            'finance.note.manage',
            'finance.reconcile.manage',
            'finance.salary.manage',
            // P1-01：审批节点权限（会计审核 / 总经理终审）
            'finance.payment.approve',
            'finance.invoice.approve',
            'rnd.extension.approve',
            'sales.return.approve',
            // 行政 / 人事
            'announcement.manage',
            'hr.position.manage',
            // 工程图档 / 资料文件
            'design.file.manage',
            'file.resource.view',
            'file.resource.manage',
            'bom.view.self',                    // 本人分支 BOM 只读（设计师族）
            // 经营管理
            'dashboard.view',
            'support.view',
            // P1-02：标准成本维护（仅财务/总经理可见，业务角色不可见）
            'cost.standard.view',
            'cost.standard.manage',
            // 后台管理（运维 CRUD）
            'admin.department.manage',
            'admin.position.manage',
            'admin.permission.manage',
            'admin.masterdata.manage',
            'ai.guide.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // P0-08：报表权限按业务域拆分（report.sales/quality/supplier/finance），
        // 旧的 report.view / report.export 退场（Q6 本期不做导出，避免"有导出无范围"的假验收）
        Permission::whereIn('name', ['report.view', 'report.export'])->delete();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
