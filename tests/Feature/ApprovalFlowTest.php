<?php

namespace Tests\Feature;

use App\Models\Approval\ApprovalFlow;
use App\Models\Approval\ApprovalInstance;
use App\Models\Finance\CustomerInvoice;
use App\Models\Organization\User;
use App\Models\Purchasing\PurchaseOrder;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P1-01 审批引擎接线（§9.2 / D-15 / D-21）
 *
 * 不 RefreshDatabase：用开发库演示账号，所有写操作包在事务里 finally 回滚。
 */
class ApprovalFlowTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    private function demoId(string $username): int
    {
        return (int) User::where('username', $username)->value('id');
    }

    private function makePo(int $buyerId, string $status = 'draft'): int
    {
        $supplierId = (int) DB::table('suppliers')->value('id');

        return (int) DB::table('purchase_orders')->insertGetId([
            'po_no' => 'PO-T' . substr(uniqid(), -6),
            'supplier_id' => $supplierId,
            'buyer_id' => $buyerId,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'status' => $status,
            'total_amount' => 1000,
            'received_qty' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /* ---------- Seeder 与权限矩阵 ---------- */

    public function test_six_flows_are_seeded_with_resolvable_approvers(): void
    {
        $types = ['purchase_order.confirm', 'stocktake.adjust', 'payment.execute', 'invoice.issue', 'rnd.extension', 'sales_return.approve'];
        foreach ($types as $t) {
            $flow = ApprovalFlow::where('business_type', $t)->where('status', true)->first();
            $this->assertNotNull($flow, "审批流缺失：{$t}");

            foreach ($flow->steps_json as $step) {
                $this->assertGreaterThan(0, (int) ($step['approver_id'] ?? 0), "{$t} 的审批人未解析到账号");
                foreach ($step['delegate_ids'] ?? [] as $d) {
                    $this->assertGreaterThan(0, (int) $d, "{$t} 的代批人未解析到账号");
                }
            }
        }

        // D-21：采购主管主审 + 总经理代批
        $poFlow = ApprovalFlow::where('business_type', 'purchase_order.confirm')->first();
        $this->assertSame($this->demoId('徐涛'), (int) $poFlow->steps_json[0]['approver_id']);
        $this->assertContains($this->demoId('高远'), array_map('intval', $poFlow->steps_json[0]['delegate_ids']));

        // 付款/发票两级：会计 → 总经理
        foreach (['payment.execute', 'invoice.issue'] as $t) {
            $steps = ApprovalFlow::where('business_type', $t)->first()->steps_json;
            $this->assertCount(2, $steps);
            $this->assertSame($this->demoId('陈静'), (int) $steps[0]['approver_id']);
            $this->assertSame($this->demoId('高远'), (int) $steps[1]['approver_id']);
        }
    }

    public function test_approver_roles_hold_the_new_permissions(): void
    {
        $this->assertTrue(Role::findByName('role_fin_accountant')->hasPermissionTo('finance.payment.approve'));
        $this->assertTrue(Role::findByName('role_fin_accountant')->hasPermissionTo('finance.invoice.approve'));
        $this->assertTrue(Role::findByName('role_gm')->hasPermissionTo('finance.payment.approve'));
        $this->assertTrue(Role::findByName('role_gm')->hasPermissionTo('finance.invoice.approve'));
        $this->assertTrue(Role::findByName('role_gm')->hasPermissionTo('sales.return.approve'));
        $this->assertTrue(Role::findByName('role_rnd_manager')->hasPermissionTo('rnd.extension.approve'));
        $this->assertTrue(Role::findByName('role_sal_manager')->hasPermissionTo('sales.return.approve'));

        // 申请人侧不能自己批：采购专员/仓管/销售专员都没有对应审批权
        $this->assertFalse(Role::findByName('role_pur_specialist')->hasPermissionTo('finance.payment.approve'));
        $this->assertFalse(Role::findByName('role_wh_keeper')->hasPermissionTo('inventory.adjust.approve'));

        // 执行付款的路由挂的是出纳执行权
        $route = Route::getRoutes()->getByName('finance.payments.execute');
        $this->assertNotNull($route);
        $this->assertContains('permission:finance.payment.execute', $route->gatherMiddleware());
    }

    /* ---------- 采购确认：先审批后执行 ---------- */

    public function test_purchase_confirm_stays_draft_until_approved_and_self_approval_is_blocked(): void
    {
        DB::beginTransaction();
        try {
            // 确认权属主管（P0-02），所以由主管提交；主管提交即"申请人"，不能自己批（D-21 由总经理代批）
            $manager = $this->actingAsDemo('徐涛');
            $poId = $this->makePo($manager->id);

            $this->post("/purchase/orders/{$poId}/confirm")->assertRedirect();
            $po = PurchaseOrder::withoutGlobalScopes()->find($poId);
            $this->assertSame('draft', $po->status, '审批未通过前订单不得确认');

            $instance = ApprovalInstance::where('business_type', 'purchase_order.confirm')
                ->where('business_id', $poId)->firstOrFail();
            $this->assertSame('pending', $instance->status);

            // 申请人本人批 → 拒绝
            try {
                app(ApprovalService::class)->decide($instance->id, true, '自己批');
                $this->fail('申请人自批应当被拒绝');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('申请人不得审批自己提交的单据', $e->getMessage());
            }

            // 无关岗位 → 越权
            $this->actingAsDemo('王五');
            try {
                app(ApprovalService::class)->decide($instance->id, true, '越权');
                $this->fail('非本步骤审批人应当被拒绝');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('不是该步骤的指定审批人', $e->getMessage());
            }

            // 总经理代批 → 订单 confirmed，实例 approved，写审计流水
            $this->actingAsDemo('高远');
            app(ApprovalService::class)->decide($instance->id, true, '主管出差，总经理代批');
            $this->assertSame('confirmed', PurchaseOrder::withoutGlobalScopes()->find($poId)->status);
            $this->assertSame('approved', $instance->fresh()->status);
            $this->assertTrue(
                DB::table('audit_logs')->where('target_id', $instance->id)
                    ->where('action', 'approval.approve')->exists(),
                '审批通过未写 audit_logs'
            );
        } finally {
            DB::rollBack();
        }
    }

    public function test_gm_delegated_approval_requires_reason_and_marks_delegated(): void
    {
        DB::beginTransaction();
        try {
            $manager = $this->actingAsDemo('徐涛');
            $poId = $this->makePo($manager->id);
            $this->post("/purchase/orders/{$poId}/confirm")->assertRedirect();

            $instance = ApprovalInstance::where('business_type', 'purchase_order.confirm')
                ->where('business_id', $poId)->firstOrFail();
            $svc = app(ApprovalService::class);

            // 代批不写理由 → 失败
            $this->actingAsDemo('高远');
            try {
                $svc->decide($instance->id, true, '');
                $this->fail('代批缺理由应当被拒绝');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('代批必须填写理由', $e->getMessage());
            }
            $this->assertSame('pending', $instance->fresh()->status);

            // 写理由 → 通过，实例标 delegated
            $svc->decide($instance->id, true, '主管出差，总经理代批');
            $fresh = $instance->fresh();
            $this->assertSame('approved', $fresh->status);
            $this->assertTrue((bool) $fresh->delegated, '代批未标记 delegated');
            $this->assertSame('confirmed', PurchaseOrder::withoutGlobalScopes()->find($poId)->status);
            $this->assertTrue((bool) ($fresh->logs_json[0]['delegated'] ?? false));
        } finally {
            DB::rollBack();
        }
    }

    /* ---------- 待我审 ---------- */

    public function test_approval_center_only_lists_mine(): void
    {
        DB::beginTransaction();
        try {
            $manager = $this->actingAsDemo('徐涛');
            $poId = $this->makePo($manager->id);
            $this->post("/purchase/orders/{$poId}/confirm")->assertRedirect();
            $instanceId = ApprovalInstance::where('business_id', $poId)->value('id');

            $svc = app(ApprovalService::class);
            // 申请人（主管）自己看不到自己的单
            $this->assertFalse($svc->canDecide(ApprovalInstance::find($instanceId), $manager->id));
            // 无关岗位（仓管）看不到；代批人（总经理）能看到
            $this->assertFalse($svc->canDecide(ApprovalInstance::find($instanceId), $this->demoId('王五')));
            $this->assertTrue($svc->canDecide(ApprovalInstance::find($instanceId), $this->demoId('高远')));
        } finally {
            DB::rollBack();
        }
    }

    /* ---------- 发票：缺总经理一级不得计入应收 ---------- */

    public function test_invoice_waits_for_gm_and_only_then_counts_as_unpaid(): void
    {
        DB::beginTransaction();
        try {
            $accountant = $this->actingAsDemo('陈静');
            $customerId = (int) DB::table('customers')->value('id');

            $this->post('/finance/invoices', [
                'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(),
                'amount' => 1000,
                'tax_amount' => 130,
            ])->assertRedirect();

            $invoice = CustomerInvoice::withoutGlobalScopes()->orderByDesc('id')->firstOrFail();
            $this->assertSame('pending_approval', $invoice->status, '开票后先进待审批，不得直接计入应收');

            $instance = ApprovalInstance::where('business_type', 'invoice.issue')
                ->where('business_id', $invoice->id)->firstOrFail();
            // 会计本人是制单人 → 引擎跳过第 1 级，直接停在总经理
            $this->assertSame(2, (int) $instance->current_step);
            $this->assertSame($this->demoId('高远'), (int) $instance->flow->steps_json[1]['approver_id']);

            // 会计自己批不了（会计是制单人 = 申请人，自批直接被拒）
            try {
                app(ApprovalService::class)->decide($instance->id, true, '自己审');
                $this->fail('会计不得越过总经理一级');
            } catch (\DomainException $e) {
                $this->assertStringContainsString('申请人不得审批自己提交的单据', $e->getMessage());
            }
            $this->assertSame('pending_approval', $invoice->fresh()->status);

            // 总经理批准 → 才变 unpaid
            $this->actingAsDemo('高远');
            app(ApprovalService::class)->decide($instance->id, true, '同意开票');
            $this->assertSame('unpaid', $invoice->fresh()->status);
        } finally {
            DB::rollBack();
        }
    }

    /* ---------- 付款：未批不得执行 ---------- */

    public function test_payment_cannot_be_executed_before_approval(): void
    {
        DB::beginTransaction();
        try {
            $cashier = $this->actingAsDemo('刘芳');
            $supplierId = (int) DB::table('suppliers')->value('id');

            $paymentId = (int) DB::table('payments')->insertGetId([
                'payment_no' => 'PAY-T' . substr(uniqid(), -6),
                'direction' => 'out',
                'supplier_id' => $supplierId,
                'amount' => 800,
                'method' => 'bank',
                'status' => 'pending',
                'paid_at' => now()->toDateString(),
                'user_id' => $cashier->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 未批 → 执行被拒（回跳带错误，状态不变）
            $this->from('/finance/payments')->post("/finance/payments/{$paymentId}/execute")->assertRedirect('/finance/payments');
            $this->assertSame('pending', DB::table('payments')->where('id', $paymentId)->value('status'));

            // 审批通过后 → 可执行
            DB::table('payments')->where('id', $paymentId)->update(['status' => 'approved']);
            $this->post("/finance/payments/{$paymentId}/execute")->assertRedirect();
            $this->assertSame('executed', DB::table('payments')->where('id', $paymentId)->value('status'));
        } finally {
            DB::rollBack();
        }
    }
}
