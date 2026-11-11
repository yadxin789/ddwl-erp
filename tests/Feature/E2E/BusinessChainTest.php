<?php

namespace Tests\Feature\E2E;

use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierBill;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\Organization\User;
use App\Models\Production\ProductionOrder;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Sales\SalesOrder;
use App\Services\ProductionOrderService;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\SettlementService;
use Database\Seeders\AcceptanceSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-04 端到端验收剧本（三段链路首尾勾稽）
 *
 * 一条命令：php artisan test --filter=BusinessChainTest
 * 段一 计划→完工入库：工单下达 → 领料 → 报工 → 完工入库（成品入 available）
 * 段二 订单→收款：销售订单确认 → 出货 → 开票 500 → 收款核销 → AR=0
 * 段三 采购→付款：采购确认 → 收货 → IQC 合格 → 账单 500 → 付款核销 → 应付结清
 * 末尾断言：AR=0、库存数量与流水一致、工单完工入库恰好 1 笔。
 */
class BusinessChainTest extends TestCase
{
    private array $seed = [];

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->seed(AcceptanceSeeder::class);

        $this->seed = [
            'warehouse_id' => (int) DB::table('warehouses')->where('warehouse_code', 'WH-RM')->value('id'),
            'location_id' => (int) DB::table('warehouse_locations')
                ->where('warehouse_id', DB::table('warehouses')->where('warehouse_code', 'WH-RM')->value('id'))
                ->value('id'),
        ];
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function materialId(string $code): int
    {
        return (int) Material::where('material_code', $code)->value('id');
    }

    public function test_three_chains_close_the_loop(): void
    {
        $cashier = User::where('username', '刘芳')->firstOrFail();
        $cashierId = (int) $cashier->id;

        /* ---------- 段一：计划 → 完工入库 ---------- */
        $this->actingAs(User::where('username', '吴十')->firstOrFail());
        $workOrder = ProductionOrder::where('order_no', AcceptanceSeeder::PRODUCTION_ORDER_NO)->firstOrFail();
        $production = app(ProductionOrderService::class);

        $production->release($workOrder->id);
        $production->issueMaterial($workOrder->id, $this->seed['warehouse_id'], [
            ['material_id' => $this->materialId('ACC-RM01'), 'lot_no' => '', 'qty' => 20],
            ['material_id' => $this->materialId('ACC-RM02'), 'lot_no' => '', 'qty' => 10],
        ]);
        $production->report($workOrder->id, ['operation_name' => 'SMT 贴片', 'good_qty' => 10, 'defective_qty' => 0]);
        $production->complete($workOrder->id, $this->seed['warehouse_id']);

        $finishedStock = (float) InventoryStock::where('material_id', $this->materialId('ACC-FG01'))
            ->where('status', 'available')->sum('qty');
        $this->assertEquals(10.0, $finishedStock, '完工入库：FG01 应有 10 台可用库存');

        $productionReceipts = InventoryTransaction::where('ref_type', 'production_order')
            ->where('ref_id', $workOrder->id)->where('txn_type', 'receipt')->count();
        $this->assertSame(1, $productionReceipts, '工单完工入库应恰好 1 笔流水');

        /* ---------- 段二：订单 → 收款（AR=0） ---------- */
        $this->actingAs(User::where('username', '钱七')->firstOrFail());
        $order = SalesOrder::where('order_no', AcceptanceSeeder::SALES_ORDER_NO)->firstOrFail();
        $sales = app(SalesOrderService::class);
        $sales->confirm($order->id);

        $line = $order->lines()->firstOrFail();
        $sales->ship($order->id, [$line->id => 10], ['shipment_date' => now()->toDateString()]);

        $this->assertEquals(10.0, (float) $line->fresh()->shipped_qty, '销售出货应发满 10 台');

        $this->actingAs($cashier);
        $invoice = CustomerInvoice::create([
            'invoice_no' => 'INV-ACC-001',
            'customer_id' => (int) DB::table('customers')->where('customer_code', AcceptanceSeeder::CUSTOMER_CODE)->value('id'),
            'sales_order_id' => $order->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => 500,
            'tax_amount' => 0,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'owner_id' => $cashierId,
        ]);

        $receipt = Payment::create([
            'payment_no' => 'PAY-ACC-IN',
            'direction' => 'in',
            'customer_id' => $invoice->customer_id,
            'amount' => 500,
            'method' => 'bank',
            'status' => 'executed',
            'paid_at' => now()->toDateString(),
            'user_id' => $cashierId,
        ]);

        $settlement = app(SettlementService::class);
        $settlement->allocate($receipt->id, [['invoice_id' => $invoice->id, 'amount' => 500]]);
        $settlement->apply($receipt->id);

        $this->assertSame('paid', $invoice->fresh()->status, '收款 500 后发票应结清');

        /* ---------- 段三：采购 → 付款（应付结清） ---------- */
        $this->actingAs(User::where('username', '赵六')->firstOrFail());
        $po = PurchaseOrder::withoutGlobalScopes()->where('po_no', AcceptanceSeeder::PURCHASE_ORDER_NO)->firstOrFail();
        $purchase = app(PurchaseOrderService::class);
        $purchase->confirm($po->id);

        $receiptDoc = $purchase->receive([
            'po_id' => $po->id,
            'receipt_date' => now()->toDateString(),
            'warehouse_id' => $this->seed['warehouse_id'],
            'location_id' => 0, // 与收货/质检转移使用的库位口径一致（服务内部按 location_id=0 取行）
        ], [
            ['material_id' => $this->materialId('ACC-RM01'), 'lot_no' => 'ACC-PO-LOT', 'qty' => 200],
            ['material_id' => $this->materialId('ACC-RM02'), 'lot_no' => 'ACC-PO-LOT', 'qty' => 100],
        ]);

        $purchase->inspect($receiptDoc->id, $receiptDoc->lines->map(fn ($l) => [
            'line_id' => $l->id,
            'qualified_qty' => (float) $l->qty,
            'rejected_qty' => 0,
        ])->all());

        $this->assertEquals(300.0, (float) $po->fresh()->received_qty, '采购收货后已收数量应累计 300');

        $this->actingAs($cashier);
        $bill = SupplierBill::create([
            'bill_no' => 'BILL-ACC-001',
            'supplier_id' => $po->supplier_id,
            'purchase_order_id' => $po->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => 500,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'owner_id' => $cashierId,
        ]);

        $payment = Payment::create([
            'payment_no' => 'PAY-ACC-OUT',
            'direction' => 'out',
            'supplier_id' => $po->supplier_id,
            'amount' => 500,
            'method' => 'bank',
            'status' => 'executed',
            'paid_at' => now()->toDateString(),
            'user_id' => $cashierId,
        ]);
        $settlement->allocate($payment->id, [['bill_id' => $bill->id, 'amount' => 500]]);
        $settlement->apply($payment->id);

        $this->assertSame('paid', $bill->fresh()->status, '付款 500 后供应商账单应结清');

        /* ---------- 末尾勾稽断言 ---------- */
        $arBalance = (float) CustomerInvoice::withoutGlobalScopes()
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum(DB::raw('amount - paid_amount'));
        $this->assertEquals(0.0, round($arBalance, 2), 'AR=0：应收余额应为 0');

        $apBalance = (float) SupplierBill::withoutGlobalScopes()
            ->whereIn('status', ['unpaid', 'partial'])
            ->sum(DB::raw('amount - paid_amount'));
        // 断言只针对验收单据（演示库其他历史账单不计入）
        $this->assertEquals(0.0, round((float) ($bill->fresh()->amount - $bill->fresh()->paid_amount), 2), '验收账单 AP 已结清');
        $this->assertGreaterThanOrEqual(0.0, $apBalance);

        foreach (['ACC-RM01', 'ACC-RM02', 'ACC-PK01', 'ACC-FG01'] as $code) {
            $mid = $this->materialId($code);
            $stockQty = (float) InventoryStock::where('material_id', $mid)->sum('qty');
            $txnQty = (float) InventoryTransaction::where('material_id', $mid)->sum('qty_change');
            $this->assertEquals(round($txnQty, 2), round($stockQty, 2), "库存与流水不一致：{$code}");
        }
    }
}
