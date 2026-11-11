<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Sales\CustomerReturn;
use App\Services\ApprovalService;
use App\Services\SalesReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * 职责分离（SoD）回归测试 —— 对齐 v1.3 岗位"禁区"与整改计划 P0-01~P0-04 / D-15 / D-21
 *
 * 注意：不 RefreshDatabase —— 依赖现有开发库的演示账号与角色（与 PageGuardTest 一致）。
 * 断言约定：无权限 → 403；有权限但对象不存在 → 404；有权限且对象存在才可能 200/302。
 */
class SoDTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    /**
     * 取某命名路由的中间件清单（用于断言"判定权挂在哪个权限上"）。
     * 说明：采购/收货在演示库无单据，路由模型绑定会先返回 404，
     * 因此权限断言走"路由中间件 + 角色权限"两层，避免为了 403 去造单据污染开发库。
     */
    private function routeMiddleware(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "路由不存在：{$routeName}");

        return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
    }

    private function roleHas(string $roleName, string $permission): bool
    {
        return Role::findByName($roleName)->hasPermissionTo($permission);
    }

    /* ---------- P0-01 来料检验：IQC 判定，收货岗不得判定 ---------- */

    public function test_iqc_can_open_receipts_and_reach_inspect_action(): void
    {
        $this->actingAsDemo('郑一');

        // 有 purchase.view：可看收货单列表
        $this->get('/purchase/receipts')->assertStatus(200);

        $this->assertTrue($this->roleHas('role_qc_iqc', 'quality.receipt.inspect'), 'IQC 未获得来料判定权');
    }

    public function test_inspect_route_is_guarded_by_iqc_permission_only(): void
    {
        $middleware = $this->routeMiddleware('purchase.receipts.inspect');

        $this->assertContains('permission:quality.receipt.inspect', $middleware,
            '来料判定路由未挂 quality.receipt.inspect');
        $this->assertNotContains('permission:purchase.receive', $middleware,
            '来料判定路由仍在收货权限下（收货岗可自检自判）');
    }

    public function test_warehouse_receiver_cannot_inspect(): void
    {
        $this->actingAsDemo('卢伟');

        $this->get('/purchase/receipts')->assertStatus(200);

        $this->assertFalse($this->roleHas('role_wh_receiver', 'quality.receipt.inspect'),
            '收货员不应持有来料判定权');
        $this->assertTrue($this->roleHas('role_wh_receiver', 'purchase.receive'));
    }

    public function test_warehouse_keeper_cannot_inspect(): void
    {
        $this->assertFalse($this->roleHas('role_wh_keeper', 'quality.receipt.inspect'));
        $this->assertFalse($this->roleHas('role_wh_supervisor', 'quality.receipt.inspect'));
    }

    /* ---------- P0-02 采购确认/关闭：与创建分离，主管主审 + 总经理代批 ---------- */

    public function test_confirm_and_close_are_not_under_create_permission(): void
    {
        foreach (['purchase.orders.confirm', 'purchase.orders.close'] as $name) {
            $middleware = $this->routeMiddleware($name);
            $this->assertContains('permission:purchase.confirm', $middleware, "{$name} 未挂 purchase.confirm");
            $this->assertNotContains('permission:purchase.create', $middleware, "{$name} 仍与创建同权");
        }
    }

    public function test_purchase_specialist_cannot_confirm_or_close(): void
    {
        $this->actingAsDemo('赵六');

        $this->assertFalse($this->roleHas('role_pur_specialist', 'purchase.confirm'),
            '采购专员不应持有订单确认权（v1.3 §4.10 禁区）');
        $this->assertTrue($this->roleHas('role_pur_specialist', 'purchase.create'));
    }

    public function test_purchase_manager_can_confirm(): void
    {
        $this->assertTrue($this->roleHas('role_pur_manager', 'purchase.confirm'));
    }

    public function test_gm_can_delegate_confirm(): void
    {
        // D-21：总经理持 purchase.confirm 可代批
        $this->assertTrue($this->roleHas('role_gm', 'purchase.confirm'));
    }

    /* ---------- P0-03 财务：会计不碰钱、出纳不碰票 ---------- */

    public function test_accountant_cannot_execute_payment(): void
    {
        $this->actingAsDemo('陈静');

        // 会计可看收付款页（只读）
        $this->get('/finance/payments')->assertStatus(200);

        // 但不得登记收付款（执行权只属出纳）
        $this->post('/finance/payments')->assertStatus(403);
    }

    public function test_cashier_can_execute_payment(): void
    {
        $this->actingAsDemo('刘芳');

        // 有执行权：空表单触发校验后回跳（302），不是 403
        $this->post('/finance/payments')->assertStatus(302);
    }

    public function test_cashier_cannot_issue_invoice_or_bill(): void
    {
        $this->actingAsDemo('刘芳');

        $this->post('/finance/invoices')->assertStatus(403);
        $this->post('/finance/bills')->assertStatus(403);
    }

    public function test_accountant_can_issue_invoice_and_bill(): void
    {
        $this->actingAsDemo('陈静');

        $this->post('/finance/invoices')->assertStatus(302);
        $this->post('/finance/bills')->assertStatus(302);
    }

    /* ---------- P0-04 库存调整/盘点：发起 ≠ 审批（SoD-07） ---------- */

    public function test_stocktake_create_is_warehouse_job_not_receiver(): void
    {
        $this->actingAsDemo('卢伟');
        $this->post('/production/stocktakes')->assertStatus(403);

        $this->actingAsDemo('王五');
        // 空表单：通过权限层，校验后回跳（302），不是 403
        $this->post('/production/stocktakes')->assertStatus(302);
        $this->assertTrue($this->roleHas('role_wh_keeper', 'inventory.stocktake'));
    }

    public function test_stocktake_adjuster_cannot_approve_own_adjustment(): void
    {
        $this->actingAsDemo('王五');

        // 发起权有，审批权没有：同一人不得既发起又批准
        $this->assertTrue($this->roleHas('role_wh_keeper', 'inventory.adjust.request'));
        $this->post('/inventory/adjustments/1/approve')->assertStatus(403);
        $this->post('/inventory/adjustments/1/reject', ['remark' => '自批尝试'])->assertStatus(403);

        $this->assertTrue($this->roleHas('role_wh_supervisor', 'inventory.adjust.approve'));
    }

    /* ---------- P0-04 客户退货：销售发起 ≠ 销售主管审批（SoD-08） ---------- */

    public function test_return_creator_cannot_approve_and_other_roles_are_403(): void
    {
        $this->actingAsDemo('钱七');
        $this->assertTrue($this->roleHas('role_sal_specialist', 'sales.create'));
        $this->assertFalse($this->roleHas('role_sal_specialist', 'sales.return.approve'),
            '销售专员不得审批自己发起的退货（v1.3 §4.15 禁区）');
        $this->assertTrue($this->roleHas('role_sal_manager', 'sales.return.approve'));
        $this->assertTrue($this->roleHas('role_gm', 'sales.return.approve'), '总经理可代批退货');

        DB::beginTransaction();
        try {
            $ret = app(SalesReturnService::class)->create([
                'customer_id' => (int) DB::table('customers')->value('id'),
                'return_reason' => 'SoD 测试',
            ], [[
                'material_id' => (int) DB::table('materials')->value('id'),
                'lot_no' => 'SOD' . substr((string) uniqid(), -6),
                'qty' => 1,
            ]]);

            // 创建即 pending，且尚未动库存（P1-01 改为审批后才 available→returned）
            $this->assertSame('pending', $ret->status);
            $instance = app(ApprovalService::class)->start('sales_return.approve', $ret->id);
            $this->assertNotNull($instance, '退货未挂审批实例');

            // 申请人自批 → 403（控制器对越权/自批返回 403）
            $this->post("/approvals/{$instance->id}/decide", ['pass' => 1])->assertStatus(403);

            // 无关岗位（采购）尝试审批 → 403
            $this->actingAsDemo('赵六');
            $this->post("/approvals/{$instance->id}/decide", ['pass' => 1])->assertStatus(403);

            $this->assertSame('pending', CustomerReturn::findOrFail($ret->id)->status, '被拒的审批不得改变单据状态');
        } finally {
            DB::rollBack();
        }
    }

    /* ---------- 研发与品质：提交 ≠ 审批 ---------- */

    public function test_rnd_and_quality_submitters_cannot_approve(): void
    {
        foreach (['role_eng_hw', 'role_eng_sw', 'role_eng_me', 'role_pkg_designer', 'role_id_designer'] as $role) {
            $this->assertFalse($this->roleHas($role, 'rnd.approve'), "{$role} 不得审批自己提交的 ECR/阶段");
            $this->assertFalse($this->roleHas($role, 'rnd.extension.approve'), "{$role} 不得审批自己提交的延期");
            $this->assertFalse($this->roleHas($role, 'bom.approve'), "{$role} 不得审批自己提交的 BOM");
        }
        $this->assertTrue($this->roleHas('role_rnd_manager', 'rnd.approve'));
        $this->assertTrue($this->roleHas('role_rnd_manager', 'rnd.extension.approve'));
        $this->assertTrue($this->roleHas('role_rnd_manager', 'bom.approve'));

        $this->assertTrue($this->roleHas('role_qc_iqc', 'quality.operate'));
        $this->assertFalse($this->roleHas('role_qc_iqc', 'quality.approve'), '检验员不得审批自己录入的检验结论');
        $this->assertTrue($this->roleHas('role_qc_manager', 'quality.approve'));
    }

    /* ---------- 导出：本期不做（Q6），不留"有导出无范围"的假权限 ---------- */

    public function test_no_export_permissions_are_granted(): void
    {
        $exportPermissions = \Spatie\Permission\Models\Permission::where('name', 'like', '%export%')->pluck('name');
        $this->assertSame([], $exportPermissions->all(), '本期不做导出（Q6），不应存在 export 权限');
        $this->assertFalse(Route::has('reports.export'), '不应存在导出路由');
    }
}
