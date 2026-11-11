<?php

use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ProjectTemplateController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\DesignFileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BomController;
use App\Http\Controllers\BomImportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CrmController;
use App\Http\Controllers\ObservabilityController;
use App\Http\Controllers\DevopsController;
use App\Http\Controllers\EcomOnboardingController;
use App\Http\Controllers\EcomController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceAdvancedController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\HrAdvancedController;
use App\Http\Controllers\HrController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\IssueRequestController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ApsController;
use App\Http\Controllers\ProductCenterController;
use App\Http\Controllers\ProcurementController;
use App\Http\Controllers\QualityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RndController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StandardCostController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoiceController;
use App\Http\Controllers\WecomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 认证（P0：登录加 5次/分钟 限流防爆破）
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.submit');
});

Route::middleware(['auth', \App\Http\Middleware\SetTenantContext::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/password/update', [AuthController::class, 'updatePassword'])->name('password.update');

    // 工作台
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // 主数据（RBAC：菜单级 permission）
    Route::middleware('permission:material.list')->group(function () {
        Route::get('/materials', [MaterialController::class, 'index'])->name('materials.index');
    });
    Route::middleware('permission:material.create')->group(function () {
        Route::get('/materials/create', [MaterialController::class, 'create'])->name('materials.create');
        Route::post('/materials', [MaterialController::class, 'store'])->name('materials.store');
    });
    Route::middleware('permission:material.edit')->group(function () {
        Route::get('/materials/{material}/edit', [MaterialController::class, 'edit'])->name('materials.edit');
        Route::put('/materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
    });

    Route::middleware('permission:customer.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });
    Route::middleware('permission:customer.create')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    });
    Route::middleware('permission:customer.edit')->group(function () {
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

    Route::middleware('permission:supplier.view')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    });
    Route::middleware('permission:supplier.create')->group(function () {
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    });
    Route::middleware('permission:supplier.edit')->group(function () {
        Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
    });

    // 仓储库存
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/transactions', [InventoryController::class, 'transactions'])->name('inventory.transactions');
    });

    // BOM
    Route::middleware('permission:bom.view')->group(function () {
        // P0-06：BOM 待审批（无参入口）
        Route::get('/bom/pending', [BomController::class, 'pending'])->middleware('permission:bom.approve')->name('bom.pending');
        Route::get('/bom', [BomController::class, 'index'])->name('bom.index');
        Route::get('/bom/{bom}', [BomController::class, 'show'])->whereNumber('bom')->name('bom.show');
    });
    Route::middleware('permission:bom.import')->group(function () {
        Route::get('/bom/import', [BomImportController::class, 'showUpload'])->name('bom.import');
        Route::post('/bom/import', [BomImportController::class, 'upload'])->name('bom.import.upload');
        Route::get('/bom/import/{import}/preview', [BomImportController::class, 'preview'])->name('bom.import.preview');
        Route::post('/bom/import/{import}/confirm', [BomImportController::class, 'confirm'])->name('bom.import.confirm');
        Route::post('/bom/import/row/{row}/request-material', [BomImportController::class, 'requestMaterial'])->name('bom.import.request-material');
    });
    Route::middleware('permission:bom.approve')->group(function () {
        Route::post('/bom/{bom}/approve', [BomImportController::class, 'approve'])->name('bom.approve');
    });

    // 销售管理
    Route::middleware('permission:sales.view')->group(function () {
        Route::get('/sales/quotes', [SalesController::class, 'quotes'])->name('sales.quotes');
        Route::get('/sales/orders', [SalesController::class, 'orders'])->name('sales.orders');
        Route::get('/sales/orders/{order}', [SalesController::class, 'orderShow'])->whereNumber('order')->name('sales.orders.show');
        Route::get('/sales/returns', [SalesController::class, 'returns'])->name('sales.returns');
    });
    Route::middleware('permission:sales.create')->group(function () {
        Route::get('/sales/quotes/create', [SalesController::class, 'quoteCreate'])->name('sales.quotes.create');
        Route::post('/sales/quotes', [SalesController::class, 'quoteStore'])->name('sales.quotes.store');
        Route::get('/sales/orders/create', [SalesController::class, 'orderCreate'])->name('sales.orders.create');
        Route::post('/sales/orders', [SalesController::class, 'orderStore'])->name('sales.orders.store');
        Route::post('/sales/quotes/{quote}/convert', [SalesController::class, 'quoteConvert'])->name('sales.quotes.convert');
        Route::get('/sales/returns/create', [SalesController::class, 'returnCreate'])->name('sales.returns.create');
        Route::post('/sales/returns', [SalesController::class, 'returnStore'])->name('sales.returns.store');
    });
    Route::middleware('permission:sales.confirm')->group(function () {
        Route::post('/sales/orders/{order}/confirm', [SalesController::class, 'orderConfirm'])->name('sales.orders.confirm');
    });
    Route::middleware('permission:sales.ship')->group(function () {
        Route::post('/sales/orders/{order}/ship', [SalesController::class, 'orderShip'])->name('sales.orders.ship');
    });

    // 采购管理
    Route::middleware('permission:purchase.view')->group(function () {
        Route::get('/purchase/requisitions', [PurchaseController::class, 'requisitions'])->name('purchase.requisitions');
    });
    // 采购订单列表属采购岗（purchase.order.view），品质的 purchase.view（来料检验）与电商的 procurement.view（采购计划）不含此页
    Route::middleware('permission:purchase.order.view')->group(function () {
        Route::get('/purchase/orders', [PurchaseController::class, 'orders'])->name('purchase.orders');
        Route::get('/purchase/orders/{order}', [PurchaseController::class, 'orderShow'])->whereNumber('order')->name('purchase.orders.show');
    });
    // 收货单：仓库（purchase.receive）+ 采购（purchase.view）都能看
    Route::middleware('permission:purchase.view|purchase.receive')->group(function () {
        // P0-06：收货作业台（无参入口，替代原被导航跳过的「收货上架 / 送检」带参映射）
        Route::get('/purchase/receipts/pending', [PurchaseController::class, 'putawayQueue'])->name('purchase.receipts.pending');
        Route::get('/purchase/receipts/inspect-queue', [PurchaseController::class, 'inspectQueue'])->name('purchase.receipts.inspect-queue');
        Route::get('/purchase/receipts', [PurchaseController::class, 'receipts'])->name('purchase.receipts');
        Route::get('/purchase/receipts/{receipt}', [PurchaseController::class, 'receiptShow'])->whereNumber('receipt')->name('purchase.receipts.show');
    });
    Route::middleware('permission:purchase.create')->group(function () {
        Route::get('/purchase/requisitions/create', [PurchaseController::class, 'requisitionCreate'])->name('purchase.requisitions.create');
        Route::post('/purchase/requisitions', [PurchaseController::class, 'requisitionStore'])->name('purchase.requisitions.store');
        Route::get('/purchase/orders/create', [PurchaseController::class, 'orderCreate'])->name('purchase.orders.create');
        Route::post('/purchase/orders', [PurchaseController::class, 'orderStore'])->name('purchase.orders.store');
    });
    // 确认/关闭不与创建同权（v1.3 §4.10 禁区）：主管主审，总经理可代批（D-21）
    Route::middleware('permission:purchase.confirm')->group(function () {
        Route::post('/purchase/orders/{order}/confirm', [PurchaseController::class, 'orderConfirm'])->name('purchase.orders.confirm');
        Route::post('/purchase/orders/{order}/close', [PurchaseController::class, 'orderClose'])->name('purchase.orders.close');
    });
    Route::middleware('permission:purchase.receive')->group(function () {
        Route::get('/purchase/orders/{order}/receive', [PurchaseController::class, 'receiveCreate'])->name('purchase.receive.create');
        Route::post('/purchase/receive', [PurchaseController::class, 'receiveStore'])->name('purchase.receive.store');
    });
    // 来料判定归 IQC（v1.3 §4.6 主责 / §4.22 收货员禁区），与收货权分离
    Route::middleware('permission:quality.receipt.inspect')->group(function () {
        Route::post('/purchase/receipts/{receipt}/inspect', [PurchaseController::class, 'inspect'])->name('purchase.receipts.inspect');
    });

    // 生产管理
    Route::middleware('permission:production.view')->group(function () {
        Route::get('/production/work-centers', [ProductionController::class, 'workCenters'])->name('production.work-centers');
        Route::get('/production/routings', [ProductionController::class, 'routings'])->name('production.routings');
        Route::get('/production/aps', [ApsController::class, 'index'])->name('production.aps');
    });
    // 生产工单列表：只有生产岗能看（production.order.view），工程师的 production.view 不含此页
    Route::middleware('permission:production.order.view')->group(function () {
        Route::get('/production/orders', [ProductionController::class, 'orders'])->name('production.orders');
        Route::get('/production/orders/{order}', [ProductionController::class, 'orderShow'])->whereNumber('order')->name('production.orders.show');
    });
    // 领料发料页：生产岗看记录、仓管按 production.issue 发料（v1.3 §4.22）
    Route::middleware('permission:production.issue|production.view')->group(function () {
        Route::get('/production/issues', [ProductionController::class, 'issues'])->name('production.issues');
    });
    // 盘点归仓储职责（v1.3 §7.3 归属迁移：原挂 production.view，仓储岗进不去）
    Route::middleware('permission:inventory.stocktake')->group(function () {
        Route::get('/production/stocktakes', [ProductionController::class, 'stocktakes'])->name('production.stocktakes');
        Route::get('/production/stocktakes/{stocktake}', [ProductionController::class, 'stocktakeShow'])->whereNumber('stocktake')->name('production.stocktakes.show');
    });
    Route::middleware('permission:production.create')->group(function () {
        Route::post('/production/work-centers', [ProductionController::class, 'workCenterStore'])->name('production.work-centers.store');
        Route::post('/production/routings', [ProductionController::class, 'routingStore'])->name('production.routings.store');
        Route::post('/production/aps', [ApsController::class, 'schedule'])->name('production.aps.schedule');
        Route::get('/production/orders/create', [ProductionController::class, 'orderCreate'])->name('production.orders.create');
        Route::post('/production/orders', [ProductionController::class, 'orderStore'])->name('production.orders.store');
    });
    Route::middleware('permission:production.release')->group(function () {
        Route::post('/production/orders/{order}/release', [ProductionController::class, 'orderRelease'])->name('production.orders.release');
    });
    Route::middleware('permission:production.report')->group(function () {
        // P0-06：我的报工（无参入口）
        Route::get('/production/reports', [ProductionController::class, 'reports'])->name('production.reports');
        Route::post('/production/orders/{order}/issue', [ProductionController::class, 'issueMaterial'])->name('production.orders.issue');
        Route::post('/production/orders/{order}/report', [ProductionController::class, 'report'])->name('production.orders.report');
        Route::post('/production/orders/{order}/complete', [ProductionController::class, 'complete'])->name('production.orders.complete');
    });
    Route::middleware('permission:inventory.adjust')->group(function () {
        Route::post('/production/stocktakes', [ProductionController::class, 'stocktakeCreate'])->name('production.stocktakes.create');
    });
    // P0-04：盘点差异的"发起"归仓管/组长（inventory.adjust.request），审批另给仓库主管
    Route::middleware('permission:inventory.adjust.request')->group(function () {
        Route::post('/production/stocktakes/{stocktake}/adjust', [ProductionController::class, 'stocktakeAdjust'])->whereNumber('stocktake')->name('production.stocktakes.adjust');
        Route::post('/inventory/adjustments', [InventoryController::class, 'adjustmentStore'])->name('inventory.adjustments.store');
    });
    Route::middleware('permission:inventory.adjust.approve')->group(function () {
        Route::post('/inventory/adjustments/{adjustment}/approve', [InventoryController::class, 'adjustmentApprove'])->whereNumber('adjustment')->name('inventory.adjustments.approve');
        Route::post('/inventory/adjustments/{adjustment}/reject', [InventoryController::class, 'adjustmentReject'])->whereNumber('adjustment')->name('inventory.adjustments.reject');
    });

    /* ---------- v1.3 S6 前段：各岗位自己的作业页（消除"借别人页面干活"的后门） ---------- */

    // G1 发货作业台（仓管/仓库主管），替代此前借用销售订单详情页发货
    Route::middleware('permission:sales.ship')->group(function () {
        Route::get('/shipping', [ShippingController::class, 'index'])->name('shipping.index');
    });

    // G2 库存调整（仓储岗）：页面只读+发起，审批在独立权限下
    Route::middleware('permission:inventory.adjust|inventory.adjust.request|inventory.adjust.approve')->group(function () {
        Route::get('/inventory/adjustments', [InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    });

    // G3 仓库库位（仓储岗）
    Route::middleware('permission:warehouse.view')->group(function () {
        Route::get('/warehouse', [WarehouseController::class, 'index'])->name('warehouse.index');
    });
    Route::middleware('permission:warehouse.edit')->group(function () {
        Route::post('/warehouse/locations', [WarehouseController::class, 'locationStore'])->name('warehouse.locations.store');
        Route::post('/warehouse/locations/{location}/toggle', [WarehouseController::class, 'locationToggle'])
            ->whereNumber('location')->name('warehouse.locations.toggle');
    });

    // G9 领料申请（生产组长申请 → 超耗由生产主管审批 → 仓管发料后回填）
    Route::middleware('permission:production.issue.request')->group(function () {
        Route::get('/production/issue-requests', [IssueRequestController::class, 'index'])->name('production.issue-requests');
        Route::get('/production/issue-requests/create', [IssueRequestController::class, 'create'])->name('production.issue-requests.create');
        Route::post('/production/issue-requests', [IssueRequestController::class, 'store'])->name('production.issue-requests.store');
    });
    Route::middleware('permission:production.issue.approve')->group(function () {
        Route::post('/production/issue-requests/{issueRequest}/approve', [IssueRequestController::class, 'approve'])
            ->whereNumber('issueRequest')->name('production.issue-requests.approve');
        Route::post('/production/issue-requests/{issueRequest}/reject', [IssueRequestController::class, 'reject'])
            ->whereNumber('issueRequest')->name('production.issue-requests.reject');
    });
    Route::middleware('permission:production.issue')->group(function () {
        Route::post('/production/issue-requests/{issueRequest}/issued', [IssueRequestController::class, 'markIssued'])
            ->whereNumber('issueRequest')->name('production.issue-requests.mark-issued');
    });

    // G6 / G12 / G13 / G14 协同自助页（全岗共享）
    Route::get('/project/my', [WorkspaceController::class, 'myProjects'])->name('project.my');
    Route::get('/profile/wecom', [WorkspaceController::class, 'wecom'])->name('profile.wecom');
    // 资料文件：全员可看（共享菜单），上传单独收权限
    Route::get('/files/resources', [WorkspaceController::class, 'files'])->name('file.resources');
    Route::middleware('permission:file.resource.manage')->group(function () {
        Route::post('/files/resources', [WorkspaceController::class, 'fileUpload'])->name('file.resources.store');
    });
    // P0-07 / B-02：统一下载入口（登录即可，业务授权在 MediaFilePolicy）
    Route::get('/files/{media}/download', [WorkspaceController::class, 'fileDownload'])
        ->whereNumber('media')->name('files.download');
    // 在线预览：只放行图片/Excel/PDF/Office，详见 FileService::PREVIEWABLE_EXTENSIONS
    Route::get('/files/{media}/preview', [WorkspaceController::class, 'filePreview'])
        ->whereNumber('media')->name('files.preview');

    /* ---------- v1.3 S7 后台管理（管理员专属，纯运维，不含任何业务操作） ---------- */

    Route::middleware('permission:admin.department.manage')->group(function () {
        Route::get('/admin/departments', [AdminController::class, 'departments'])->name('admin.departments');
        Route::post('/admin/departments', [AdminController::class, 'departmentStore'])->name('admin.departments.store');
        Route::put('/admin/departments/{department}', [AdminController::class, 'departmentUpdate'])
            ->whereNumber('department')->name('admin.departments.update');
    });

    Route::middleware('permission:admin.position.manage')->group(function () {
        Route::get('/admin/positions', [AdminController::class, 'positions'])->name('admin.positions');
        Route::post('/admin/positions', [AdminController::class, 'positionStore'])->name('admin.positions.store');
        Route::put('/admin/positions/{position}', [AdminController::class, 'positionUpdate'])
            ->whereNumber('position')->name('admin.positions.update');
    });

    Route::middleware('permission:admin.masterdata.manage')->group(function () {
        Route::get('/admin/master-data', [MasterDataController::class, 'index'])->name('admin.master-data');
        Route::put('/admin/master-data/domains/{domain}', [MasterDataController::class, 'updateDomain'])
            ->whereNumber('domain')->name('admin.master-data.domains.update');

        Route::get('/admin/master-data/task-types', [MasterDataController::class, 'taskTypes'])
            ->name('admin.master-data.task-types');
        Route::post('/admin/master-data/task-types', [MasterDataController::class, 'taskTypeStore'])
            ->name('admin.master-data.task-types.store');
        Route::put('/admin/master-data/task-types/{taskType}', [MasterDataController::class, 'taskTypeUpdate'])
            ->whereNumber('taskType')->name('admin.master-data.task-types.update');
        Route::delete('/admin/master-data/task-types/{taskType}', [MasterDataController::class, 'taskTypeDestroy'])
            ->whereNumber('taskType')->name('admin.master-data.task-types.destroy');

        Route::get('/admin/master-data/units', [MasterDataController::class, 'units'])->name('admin.master-data.units');
        Route::post('/admin/master-data/units', [MasterDataController::class, 'unitStore'])->name('admin.master-data.units.store');
        Route::put('/admin/master-data/units/{unit}', [MasterDataController::class, 'unitUpdate'])
            ->whereNumber('unit')->name('admin.master-data.units.update');
        Route::delete('/admin/master-data/units/{unit}', [MasterDataController::class, 'unitDestroy'])
            ->whereNumber('unit')->name('admin.master-data.units.destroy');

        Route::get('/admin/master-data/tax-terms', [MasterDataController::class, 'taxTerms'])->name('admin.master-data.tax-terms');
        Route::post('/admin/master-data/tax-entities', [MasterDataController::class, 'taxEntityStore'])->name('admin.master-data.tax-entities.store');
        Route::put('/admin/master-data/tax-entities/{entity}', [MasterDataController::class, 'taxEntityUpdate'])
            ->whereNumber('entity')->name('admin.master-data.tax-entities.update');
        Route::delete('/admin/master-data/tax-entities/{entity}', [MasterDataController::class, 'taxEntityDestroy'])
            ->whereNumber('entity')->name('admin.master-data.tax-entities.destroy');
        Route::post('/admin/master-data/tax-rates', [MasterDataController::class, 'taxRateStore'])->name('admin.master-data.tax-rates.store');
        Route::put('/admin/master-data/tax-rates/{rate}', [MasterDataController::class, 'taxRateUpdate'])
            ->whereNumber('rate')->name('admin.master-data.tax-rates.update');
        Route::delete('/admin/master-data/tax-rates/{rate}', [MasterDataController::class, 'taxRateDestroy'])
            ->whereNumber('rate')->name('admin.master-data.tax-rates.destroy');
        Route::post('/admin/master-data/payment-terms', [MasterDataController::class, 'paymentTermStore'])->name('admin.master-data.payment-terms.store');
        Route::put('/admin/master-data/payment-terms/{term}', [MasterDataController::class, 'paymentTermUpdate'])
            ->whereNumber('term')->name('admin.master-data.payment-terms.update');
        Route::delete('/admin/master-data/payment-terms/{term}', [MasterDataController::class, 'paymentTermDestroy'])
            ->whereNumber('term')->name('admin.master-data.payment-terms.destroy');

        Route::get('/admin/master-data/approval-flows', [MasterDataController::class, 'approvalFlows'])->name('admin.master-data.approval-flows');
        Route::get('/admin/master-data/approval-flows/create', [MasterDataController::class, 'approvalFlowCreate'])->name('admin.master-data.approval-flows.create');
        Route::post('/admin/master-data/approval-flows', [MasterDataController::class, 'approvalFlowStore'])->name('admin.master-data.approval-flows.store');
        Route::get('/admin/master-data/approval-flows/{flow}/edit', [MasterDataController::class, 'approvalFlowEdit'])
            ->whereNumber('flow')->name('admin.master-data.approval-flows.edit');
        Route::put('/admin/master-data/approval-flows/{flow}', [MasterDataController::class, 'approvalFlowUpdate'])
            ->whereNumber('flow')->name('admin.master-data.approval-flows.update');
        Route::delete('/admin/master-data/approval-flows/{flow}', [MasterDataController::class, 'approvalFlowDestroy'])
            ->whereNumber('flow')->name('admin.master-data.approval-flows.destroy');

        Route::get('/admin/master-data/number-sequences', [MasterDataController::class, 'numberSequences'])->name('admin.master-data.number-sequences');
        Route::put('/admin/master-data/number-sequences/{sequence}', [MasterDataController::class, 'numberSequenceUpdate'])
            ->whereNumber('sequence')->name('admin.master-data.number-sequences.update');
    });

    Route::middleware('permission:admin.permission.manage')->group(function () {
        Route::get('/admin/permissions', [AdminController::class, 'permissions'])->name('admin.permissions');
        Route::get('/admin/permissions/{role}', [AdminController::class, 'rolePermissions'])
            ->whereNumber('role')->name('admin.permissions.role');
        Route::put('/admin/permissions/{role}', [AdminController::class, 'rolePermissionsUpdate'])
            ->whereNumber('role')->name('admin.permissions.role.update');
    });

    Route::middleware('permission:ai.guide.manage')->group(function () {
        Route::get('/admin/ai-guide', [AdminController::class, 'aiGuide'])->name('ai.guide');
        Route::post('/admin/ai-guide', [AdminController::class, 'aiProviderStore'])->name('ai.guide.providers.store');
        Route::post('/admin/ai-guide/{provider}/toggle', [AdminController::class, 'aiProviderToggle'])
            ->whereNumber('provider')->name('ai.guide.providers.toggle');
    });

    /* ---------- v1.3 S6b 缺口页：报表 / 驾驶舱 / 编制 / 图档 / 公告 ---------- */

    Route::middleware('permission:report.sales|report.quality|report.supplier|report.finance')->group(function () {
        Route::get('/reports', [ReportController::class, 'center'])->name('report.center');
    });
    Route::get('/reports/sales', [ReportController::class, 'sales'])->middleware('permission:report.sales')->name('report.sales');
    Route::get('/reports/quality', [ReportController::class, 'quality'])->middleware('permission:report.quality')->name('report.quality');
    Route::get('/reports/supplier', [ReportController::class, 'supplier'])->middleware('permission:report.supplier')->name('report.supplier');
    Route::post('/reports/supplier/score', [ReportController::class, 'scoreSupplier'])->middleware('permission:report.supplier')->name('report.supplier.score');
    Route::get('/reports/finance', [ReportController::class, 'finance'])->middleware('permission:report.finance')->name('report.finance');

    // 老板驾驶舱：只读汇总，dashboard.view 只发给总经理岗
    Route::get('/dashboard/exec', [HomeController::class, 'exec'])
        ->middleware('permission:dashboard.view')->name('dashboard.exec');

    Route::middleware('permission:design.file.manage')->group(function () {
        Route::get('/design/pkg-files', [DesignFileController::class, 'packaging'])->name('design.pkg-files');
        Route::get('/design/id-files', [DesignFileController::class, 'identity'])->name('design.id-files');
    });

    Route::get('/hr/positions', [HrController::class, 'positions'])
        ->middleware('permission:hr.position.manage')->name('hr.positions');

    // 公告：全员可读（菜单已挂在每个岗位），发布与下架需 announcement.manage
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcement.index');
    Route::middleware('permission:announcement.manage')->group(function () {
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcement.store');
        Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])
            ->whereNumber('announcement')->name('announcement.toggle');
    });

    // 出纳 / 会计五页（G10）
    // GET 收付款页：出纳可执行、会计可查看（表单按权限隐藏）；POST 只给执行权 finance.payment.execute
    Route::middleware('permission:finance.payment.execute|finance.payment.view')->group(function () {
        Route::get('/finance/payments', [FinanceController::class, 'payments'])->name('finance.payment.execute');
    });
    Route::middleware('permission:finance.payment.execute')->group(function () {
        Route::post('/finance/payments', [FinanceController::class, 'paymentStore'])->name('finance.payments.store');
        // P1-01：执行付款（必须审批通过后的 approved 状态才能执行）
        Route::post('/finance/payments/{payment}/execute', [FinanceController::class, 'paymentExecute'])
            ->whereNumber('payment')->name('finance.payments.execute');
    });

    // P1-02 标准成本：维护（仅财务 manage）与差异分析（财务/总经理 view）
    Route::middleware('permission:cost.standard.manage')->group(function () {
        Route::get('/finance/standard-costs', [StandardCostController::class, 'index'])->name('finance.standard-costs');
        Route::post('/finance/standard-costs', [StandardCostController::class, 'store'])->name('finance.standard-costs.store');
        Route::post('/finance/standard-costs/deactivate', [StandardCostController::class, 'deactivate'])->name('finance.standard-costs.deactivate');
    });
    Route::middleware('permission:cost.standard.view')->group(function () {
        Route::get('/finance/cost-variance', [StandardCostController::class, 'variance'])->name('finance.cost-variance');
    });

    Route::middleware('permission:finance.cashbook.manage')->group(function () {
        Route::get('/finance/cashbook', [FinanceController::class, 'cashbook'])->name('finance.cashbook');
        Route::post('/finance/cashbook', [FinanceController::class, 'cashbookStore'])->name('finance.cashbook.store');
    });

    Route::middleware('permission:finance.bank.reconcile')->group(function () {
        Route::get('/finance/bank-reconcile', [FinanceController::class, 'bankReconcile'])->name('finance.bank-reconcile');
        Route::post('/finance/bank-reconcile', [FinanceController::class, 'reconcileStore'])->name('finance.bank-reconcile.store');
    });

    Route::get('/finance/arap', [FinanceController::class, 'arap'])
        ->middleware('permission:finance.arap.view')->name('finance.arap');

    // P1-03：供应商三单匹配差异表（会计）
    Route::get('/finance/three-way-match', [FinanceController::class, 'threeWayMatch'])
        ->middleware('permission:finance.reconcile.manage')->name('finance.three-way-match');

    Route::middleware('permission:finance.note.manage')->group(function () {
        Route::get('/finance/notes', [FinanceController::class, 'notes'])->name('finance.notes');
        Route::post('/finance/notes', [FinanceController::class, 'noteStore'])->name('finance.notes.store');
    });

    // 项目模板（G11）
    Route::middleware('permission:project.template.manage')->group(function () {
        Route::get('/project-templates', [ProjectTemplateController::class, 'index'])->name('project.templates');
        Route::get('/project-templates/create', [ProjectTemplateController::class, 'create'])->name('project.templates.create');
        Route::post('/project-templates', [ProjectTemplateController::class, 'store'])->name('project.templates.store');
        Route::get('/project-templates/{template}', [ProjectTemplateController::class, 'show'])
            ->whereNumber('template')->name('project.templates.show');
        Route::put('/project-templates/{template}', [ProjectTemplateController::class, 'update'])
            ->whereNumber('template')->name('project.templates.update');
        Route::delete('/project-templates/{template}', [ProjectTemplateController::class, 'destroy'])
            ->whereNumber('template')->name('project.templates.destroy');
        Route::post('/project-templates/{template}/apply', [ProjectTemplateController::class, 'apply'])
            ->whereNumber('template')->name('project.templates.apply');
    });

    // 创建研发项目页导入模板配置（页面本身在 rnd.create 权限组内）
    Route::get('/project-templates/{template}/config', [ProjectTemplateController::class, 'config'])
        ->whereNumber('template')
        ->middleware('permission:rnd.create')
        ->name('project.templates.config');

    // 支援中心（G12）
    Route::get('/support', [SupportController::class, 'index'])
        ->middleware('permission:support.view')->name('support.center');

    // 研发项目
    Route::middleware('permission:rnd.view')->group(function () {
        // P0-06：我的任务（无参入口，替代原指向 rnd.projects.show 的带参映射）
        Route::get('/rnd/my-tasks', [RndController::class, 'myTasks'])->name('rnd.my-tasks');
        Route::get('/rnd/projects', [RndController::class, 'projects'])->name('rnd.projects');
        Route::get('/rnd/projects/{project}', [RndController::class, 'projectShow'])->whereNumber('project')->name('rnd.projects.show');
        Route::get('/rnd/task-types', [RndController::class, 'taskTypes'])->name('rnd.task-types');
    });
    Route::middleware('permission:rnd.create')->group(function () {
        // P0-06：交付物上传台（无参入口）
        Route::get('/rnd/documents', [RndController::class, 'documents'])->name('rnd.documents');
        Route::get('/rnd/projects/create', [RndController::class, 'projectCreate'])->name('rnd.projects.create');
        Route::post('/rnd/projects', [RndController::class, 'projectStore'])->name('rnd.projects.store');
        Route::post('/rnd/projects/{project}/tasks', [RndController::class, 'taskStore'])->name('rnd.tasks.store');
        Route::post('/rnd/tasks/{task}/documents', [RndController::class, 'documentUpload'])->name('rnd.documents.upload');
        Route::post('/rnd/documents/{document}/audit', [RndController::class, 'documentAudit'])->name('rnd.documents.audit');
        Route::post('/rnd/tasks/{task}/extensions', [RndController::class, 'extensionStore'])->name('rnd.extensions.store');

        // P1-05：阶段推进（试产需基线）、设基线、简版 ECR/ECO
        Route::post('/rnd/projects/{project}/stage', [RndController::class, 'projectStage'])->whereNumber('project')->name('rnd.projects.stage');
        Route::post('/rnd/documents/{document}/baseline', [RndController::class, 'documentBaseline'])->whereNumber('document')->name('rnd.documents.baseline');
        Route::post('/rnd/projects/{project}/ecr', [RndController::class, 'ecrStore'])->whereNumber('project')->name('rnd.ecr.store');
        Route::get('/rnd/projects/{project}/ecr/impact', [RndController::class, 'ecrImpact'])->whereNumber('project')->name('rnd.ecr.impact');

        // P1-05：我的任务 开始 / 提交 / 审核
        Route::post('/rnd/tasks/{task}/start', [RndController::class, 'taskStart'])->whereNumber('task')->name('rnd.tasks.start');
        Route::post('/rnd/tasks/{task}/submit', [RndController::class, 'taskSubmit'])->whereNumber('task')->name('rnd.tasks.submit');
        Route::post('/rnd/tasks/{task}/decide', [RndController::class, 'taskDecide'])->whereNumber('task')->name('rnd.tasks.decide');
    });
    Route::middleware('permission:rnd.approve')->group(function () {
        // P0-06：交付物审核台（无参入口）
        Route::get('/rnd/documents/review', [RndController::class, 'documentReview'])->name('rnd.documents.review');
    });

    // 协同办公（个人工作台，仅 auth）
    Route::get('/profile', [OfficeController::class, 'profile'])->name('office.profile');
    Route::post('/profile', [OfficeController::class, 'profileUpdate'])->name('office.profile.update');
    Route::get('/profile/avatar', [OfficeController::class, 'avatar'])->name('office.avatar');
    Route::post('/profile/avatar', [OfficeController::class, 'avatarUpdate'])->name('office.avatar.update');
    Route::get('/notifications', [OfficeController::class, 'notifications'])->name('office.notifications');
    Route::get('/todos', [OfficeController::class, 'todos'])->name('office.todos');
    Route::post('/todos/{todo}/finish', [OfficeController::class, 'todoFinish'])->name('office.todos.finish');
    Route::get('/knowledge', [OfficeController::class, 'knowledge'])->name('office.knowledge');
    Route::post('/knowledge/upload', [OfficeController::class, 'knowledgeUpload'])->name('office.knowledge.upload');
    Route::get('/chat/rooms', [OfficeController::class, 'chatRooms'])->name('office.chat.rooms');
    Route::get('/chat/rooms/{room}', [OfficeController::class, 'chatShow'])->name('office.chat.show');
    Route::post('/chat/rooms/{room}/send', [OfficeController::class, 'chatSend'])->name('office.chat.send');
    Route::get('/profile/password', [OfficeController::class, 'password'])->name('office.password');

    // 审批中心（个人待办，仅 auth；审批人由 Service 层强制校验）
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{approval}/decide', [ApprovalController::class, 'decide'])->name('approvals.decide');

    // AI 对话
    Route::middleware('permission:ai.view')->group(function () {
        Route::get('/ai/chat', [AiChatController::class, 'index'])->name('ai.chat');
        Route::post('/ai/chat/send', [AiChatController::class, 'send'])->name('ai.chat.send');
        Route::post('/ai/conversations', [AiChatController::class, 'storeConversation'])->name('ai.conversations.store');
        Route::patch('/ai/conversations/{conversation}', [AiChatController::class, 'updateConversation'])
            ->whereNumber('conversation')->name('ai.conversations.update');
        Route::delete('/ai/conversations/{conversation}', [AiChatController::class, 'destroyConversation'])
            ->whereNumber('conversation')->name('ai.conversations.destroy');
        Route::post('/ai/conversations/{conversation}/clear', [AiChatController::class, 'clearConversation'])
            ->whereNumber('conversation')->name('ai.conversations.clear');
        Route::get('/ai/conversations/{conversation}/export', [AiChatController::class, 'exportConversation'])
            ->whereNumber('conversation')->name('ai.conversations.export');
        Route::get('/ai/attachments/{attachment}/preview', [AiChatController::class, 'previewAttachment'])
            ->whereNumber('attachment')->name('ai.attachments.preview');
        Route::get('/ai/attachments/{attachment}/download', [AiChatController::class, 'downloadAttachment'])
            ->whereNumber('attachment')->name('ai.attachments.download');
        Route::get('/ai/ocr', [AiChatController::class, 'ocr'])->name('ai.ocr');
    });
    Route::middleware('permission:ai.provider.manage')->group(function () {
        Route::get('/ai/providers', [AiChatController::class, 'providers'])->name('ai.providers');
        Route::post('/ai/providers', [AiChatController::class, 'providerStore'])->name('ai.providers.store');
        Route::post('/ai/providers/{provider}/toggle', [AiChatController::class, 'providerToggle'])->whereNumber('provider')->name('ai.providers.toggle');
        Route::post('/ai/providers/{provider}/test', [AiChatController::class, 'providerTest'])->whereNumber('provider')->name('ai.providers.test');
    });

    // 质量检验
    Route::middleware('permission:quality.inspection.view')->group(function () {
        Route::get('/quality/pending', [QualityController::class, 'pending'])->name('quality.pending');
        Route::get('/quality/inspections', [QualityController::class, 'inspections'])->name('quality.inspections');
        Route::get('/quality/inspections/create', [QualityController::class, 'inspectionCreate'])->name('quality.inspections.create');
    });
    Route::middleware('permission:quality.template.view')->group(function () {
        Route::get('/quality/templates', [QualityController::class, 'templates'])->name('quality.templates');
    });
    Route::middleware('permission:quality.defect.view')->group(function () {
        Route::get('/quality/defects', [QualityController::class, 'defects'])->name('quality.defects');
    });
    Route::middleware('permission:quality.operate')->group(function () {
        Route::post('/quality/templates', [QualityController::class, 'templateStore'])->name('quality.templates.store');
        Route::post('/quality/inspections', [QualityController::class, 'inspectionStore'])->name('quality.inspections.store');
        Route::post('/quality/defects', [QualityController::class, 'defectStore'])->name('quality.defects.store');
    });

    // CRM 客户关系
    Route::middleware('permission:crm.view')->group(function () {
        Route::get('/crm/leads', [CrmController::class, 'leads'])->name('crm.leads');
        Route::get('/crm/opportunities', [CrmController::class, 'opportunities'])->name('crm.opportunities');
        Route::get('/crm/follow-ups', [CrmController::class, 'followUps'])->name('crm.follow-ups');
    });
    Route::middleware('permission:crm.operate')->group(function () {
        Route::post('/crm/leads', [CrmController::class, 'leadStore'])->name('crm.leads.store');
        Route::post('/crm/opportunities', [CrmController::class, 'opportunityStore'])->name('crm.opportunities.store');
        Route::post('/crm/follow-ups', [CrmController::class, 'followUpStore'])->name('crm.follow-ups.store');
    });

    // 事件驱动
    Route::middleware('permission:system.event')->group(function () {
        Route::get('/events/outbox', [EventController::class, 'outbox'])->name('events.outbox');
        Route::get('/events/domain', [EventController::class, 'domainEvents'])->name('events.domain');
    });

    // 平台扩展
    Route::middleware('permission:system.platform')->group(function () {
        Route::get('/platform/api-logs', [PlatformController::class, 'apiLogs'])->name('platform.api-logs');
        Route::get('/platform/status-mappings', [PlatformController::class, 'statusMappings'])->name('platform.status-mappings');
        Route::post('/platform/status-mappings', [PlatformController::class, 'mappingStore'])->name('platform.status-mappings.store');
    });

    // 电商采购计划
    Route::middleware('permission:procurement.view')->group(function () {
        Route::get('/procurement/plans', [ProcurementController::class, 'index'])->name('proc.plans');
        Route::get('/procurement/mrp', [ProcurementController::class, 'mrp'])->name('proc.mrp');
    });
    Route::middleware('permission:procurement.operate')->group(function () {
        Route::post('/procurement/plans', [ProcurementController::class, 'store'])->name('proc.plans.store');
        Route::post('/procurement/mrp/run', [ProcurementController::class, 'mrpRun'])->name('proc.mrp.run');
        Route::post('/procurement/mrp/{suggestion}/convert', [ProcurementController::class, 'mrpConvert'])->whereNumber('suggestion')->name('proc.mrp.convert');
    });

    // HR 高级（薪资属敏感数据，加 sensitive 日志）
    Route::middleware('permission:hr.view')->group(function () {
        Route::get('/hr-adv/recruitments', [HrAdvancedController::class, 'recruitments'])->name('hra.recruitments');
        Route::get('/hr-adv/performances', [HrAdvancedController::class, 'performances'])->name('hra.performances');
    });
    // 薪资核算：人事（hr.view）看编制口径，会计（finance.salary.manage）做核算
    Route::middleware('permission:hr.view|finance.salary.manage')->group(function () {
        Route::get('/hr-adv/salaries', [HrAdvancedController::class, 'salaries'])->name('hra.salaries');
    });
    Route::middleware(['permission:hr.operate', 'sensitive:hr.salary'])->group(function () {
        Route::post('/hr-adv/recruitments', [HrAdvancedController::class, 'recruitmentStore'])->name('hra.recruitments.store');
        Route::post('/hr-adv/salaries', [HrAdvancedController::class, 'salaryStore'])->name('hra.salaries.store');
        Route::post('/hr-adv/performances', [HrAdvancedController::class, 'performanceStore'])->name('hra.performances.store');
    });

    // 财务高级
    Route::middleware('permission:finance.arap.view')->group(function () {
        Route::get('/finance-adv/reconciles', [FinanceAdvancedController::class, 'reconciles'])->name('fin.reconciles');
        Route::get('/finance-adv/transactions', [FinanceAdvancedController::class, 'transactions'])->name('fin.transactions');
    });
    Route::middleware(['permission:finance.reconcile.manage', 'sensitive:finance'])->group(function () {
        Route::post('/finance-adv/reconciles', [FinanceAdvancedController::class, 'reconcileStore'])->name('fin.reconciles.store');
        Route::post('/finance-adv/transactions', [FinanceAdvancedController::class, 'transactionStore'])->name('fin.transactions.store');
    });

    // 语音会议
    Route::middleware('permission:system.voice')->group(function () {
        Route::get('/voice/rooms', [VoiceController::class, 'index'])->name('voice.rooms');
        Route::post('/voice/rooms', [VoiceController::class, 'store'])->name('voice.rooms.store');
    });

    // 企业微信
    Route::middleware('permission:system.wecom')->group(function () {
        Route::get('/wecom', [WecomController::class, 'index'])->name('wecom.index');
        Route::post('/wecom/config', [WecomController::class, 'saveConfig'])->name('wecom.config');
    });

    // 多租户
    Route::middleware('permission:system.tenant')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    });

    // 部署运维（敏感操作日志）
    Route::middleware(['permission:system.devops', 'sensitive:devops'])->group(function () {
        Route::get('/devops', [DevopsController::class, 'index'])->name('devops.index');
        Route::post('/devops/clear-cache', [DevopsController::class, 'clearCache'])->name('devops.clear-cache');
        Route::get('/system/observability', [ObservabilityController::class, 'index'])->name('system.observability');
    });

    // 同步任务
    Route::middleware('permission:system.sync')->group(function () {
        Route::get('/sync/tasks', [SyncController::class, 'index'])->name('sync.tasks');
        Route::post('/sync/tasks', [SyncController::class, 'store'])->name('sync.tasks.store');
        Route::post('/sync/tasks/{task}/toggle', [SyncController::class, 'toggle'])->whereNumber('task')->name('sync.tasks.toggle');
        Route::post('/sync/logs/{log}/replay', [ObservabilityController::class, 'replay'])->whereNumber('log')->name('sync.logs.replay');
    });

    // 营销与售后
    Route::middleware('permission:marketing.view')->group(function () {
        Route::get('/marketing/promotions', [MarketingController::class, 'promotions'])->name('marketing.promotions');
        Route::get('/marketing/coupons', [MarketingController::class, 'coupons'])->name('marketing.coupons');
        Route::get('/marketing/tickets', [MarketingController::class, 'tickets'])->name('marketing.tickets');
    });
    Route::middleware('permission:marketing.operate')->group(function () {
        Route::post('/marketing/promotions', [MarketingController::class, 'promotionStore'])->name('marketing.promotions.store');
        Route::post('/marketing/coupons', [MarketingController::class, 'couponStore'])->name('marketing.coupons.store');
        Route::post('/marketing/tickets', [MarketingController::class, 'ticketStore'])->name('marketing.tickets.store');
    });

    // 电商商品中心
    Route::middleware('permission:product.view')->group(function () {
        Route::get('/product/categories', [ProductCenterController::class, 'categories'])->name('product.categories');
        Route::get('/product/brands', [ProductCenterController::class, 'brands'])->name('product.brands');
        Route::get('/product/spus', [ProductCenterController::class, 'spus'])->name('product.spus');
    });
    Route::middleware('permission:product.operate')->group(function () {
        Route::post('/product/categories', [ProductCenterController::class, 'categoryStore'])->name('product.categories.store');
        Route::post('/product/brands', [ProductCenterController::class, 'brandStore'])->name('product.brands.store');
        Route::post('/product/spus', [ProductCenterController::class, 'spuStore'])->name('product.spus.store');
    });

    // HR 人力资源
    Route::middleware('permission:hr.view')->group(function () {
        Route::get('/hr/departments', [HrController::class, 'departments'])->name('hr.departments');
        Route::get('/hr/employees', [HrController::class, 'employees'])->name('hr.employees');
        Route::get('/hr/leaves', [HrController::class, 'leaves'])->name('hr.leaves');
    });
    Route::middleware('permission:hr.operate')->group(function () {
        Route::post('/hr/departments', [HrController::class, 'departmentStore'])->name('hr.departments.store');
        Route::post('/hr/employees', [HrController::class, 'employeeStore'])->name('hr.employees.store');
        Route::post('/hr/leaves', [HrController::class, 'leaveStore'])->name('hr.leaves.store');
    });

    // 电商订单
    Route::middleware('permission:ecom.view')->group(function () {
        Route::get('/ecom/platforms', [EcomController::class, 'platforms'])->name('ecom.platforms');
        Route::get('/ecom/orders', [EcomController::class, 'orders'])->name('ecom.orders');

        // P1-07：接入向导（第 1 步选平台）+ 平台设置页
        Route::get('/ecom/onboarding', [EcomOnboardingController::class, 'index'])->name('ecom.onboarding');
        Route::get('/ecom/onboarding/custom', [EcomOnboardingController::class, 'customForm'])->name('ecom.onboarding.custom');
        Route::get('/ecom/onboarding/{platformKey}', [EcomOnboardingController::class, 'credentials'])->name('ecom.onboarding.credentials');
        Route::get('/ecom/instances', [EcomOnboardingController::class, 'instances'])->name('ecom.instances');
    });
    Route::middleware('permission:ecom.operate')->group(function () {
        Route::post('/ecom/platforms', [EcomController::class, 'platformStore'])->name('ecom.platforms.store');

        // P1-07：第 2~3 步（凭证 → 自检保存）+ 自定义平台 + 实例启停
        Route::post('/ecom/onboarding/custom', [EcomOnboardingController::class, 'customStore'])->name('ecom.onboarding.custom.store');
        Route::post('/ecom/onboarding/{platformKey}', [EcomOnboardingController::class, 'store'])->name('ecom.onboarding.store');
        Route::post('/ecom/instances/{instance}/toggle', [EcomOnboardingController::class, 'toggle'])->whereNumber('instance')->name('ecom.instances.toggle');

        // P3-08 交易闭环：拉单 / 对账 / 发货回写 outbox 处理
        Route::post('/ecom/orders/sync', [EcomController::class, 'syncOrders'])->name('ecom.orders.sync');
        Route::post('/ecom/orders/reconcile', [EcomController::class, 'reconcile'])->name('ecom.orders.reconcile');
        Route::post('/ecom/outbox/dispatch', [EcomController::class, 'dispatchOutbox'])->name('ecom.outbox.dispatch');
        Route::post('/ecom/outbox/retry-dead', [EcomController::class, 'retryDeadOutbox'])->name('ecom.outbox.retry-dead');
    });

    // 财务应收应付
    Route::middleware('permission:finance.arap.view')->group(function () {
        Route::get('/finance/invoices', [FinanceController::class, 'invoices'])->name('finance.invoices');
        Route::get('/finance/bills', [FinanceController::class, 'bills'])->name('finance.bills');
    });
    // 开票与账单分权（v1.3 §4.26 出纳禁区）：会计开票/录账单，出纳不得开票
    Route::middleware(['permission:finance.invoice.manage', 'sensitive:finance'])->group(function () {
        Route::post('/finance/invoices', [FinanceController::class, 'invoiceStore'])->name('finance.invoices.store');
    });
    Route::middleware(['permission:finance.bill.manage', 'sensitive:finance'])->group(function () {
        Route::post('/finance/bills', [FinanceController::class, 'billStore'])->name('finance.bills.store');
    });

    // 安全审计
    Route::middleware(['permission:system.security.view', 'sensitive:security'])->group(function () {
        Route::get('/security/logs', [SecurityController::class, 'logs'])->name('security.logs');
    });

    // 系统管理
    Route::middleware('permission:system.user.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:system.user.create')->group(function () {
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:system.user.edit')->group(function () {
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });
    Route::middleware('permission:system.user.delete')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
    Route::middleware('permission:system.role.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    });
});
