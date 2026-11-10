<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialStandardCost;
use App\Models\Organization\User;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\PurchaseOrderService;
use App\Services\SalesOrderService;
use App\Services\StandardCostService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-02 标准成本（§9.4 / D-14 / D-20）
 *
 * 不 RefreshDatabase：开发库 + 演示账号，写操作全部包事务 finally 回滚。
 * 验收样例：标准 10 元、采购 12 元入 10 件 → 价差 +20；出货 5 件结转 50；改版本不影响旧单。
 */
class CostStandardTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    /** 取/建一个可用物料 + 一条可用库存（事务内会被回滚） */
    private function makeMaterialWithStock(string $code, float $qty = 10): array
    {
        $material = Material::where('status', 1)->firstOrFail();
        $stock = DB::table('inventory_stock')->where('qty', '>', 0)->first();
        if (! $stock) {
            $this->markTestSkipped('演示库没有可复用的库存行，无法造出货场景');
        }

        DB::table('inventory_stock')->insert([
            'material_id' => $material->id,
            'lot_no' => 'LOT' . substr(uniqid(), -5),
            'warehouse_id' => $stock->warehouse_id,
            'location_id' => $stock->location_id,
            'status' => 'available',
            'qty' => $qty,
            'reserved_qty' => 0,
            'receive_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$material, $stock];
    }

    public function test_demo_versions_are_seeded_for_class_a_materials(): void
    {
        $this->assertGreaterThan(0, MaterialStandardCost::where('source', 'demo')->count(), '演示库应写入 A 类物料虚拟标准价（source=demo）');

        $demo = MaterialStandardCost::where('source', 'demo')->where('status', 'active')->first();
        $this->assertNotNull($demo);
        $this->assertGreaterThan(0, (float) $demo->standard_cost);
        $this->assertStringContainsString('演示数据', $demo->label());
    }

    public function test_new_version_supersedes_old_and_only_one_active(): void
    {
        DB::beginTransaction();
        try {
            $material = Material::where('status', 1)->firstOrFail();
            $service = app(StandardCostService::class);

            $v1 = $service->setVersion($material->id, 10.0, '2026-01-01', null, 'manual');
            $v2 = $service->setVersion($material->id, 12.0, '2026-06-01', null, 'manual');

            $this->assertEquals('superseded', $v1->fresh()->status);
            $this->assertEquals('2026-05-31', $v1->fresh()->effective_to->toDateString());
            $this->assertSame(1, MaterialStandardCost::where('material_id', $material->id)->where('status', 'active')->count());
            $this->assertEquals($v1->version + 1, $v2->version);

            // 发生时点取版本：5 月按 10 元，7 月按 12 元
            $this->assertEquals(10.0, $service->costAt($material->id, '2026-05-15', false));
            $this->assertEquals(12.0, $service->costAt($material->id, '2026-07-01', false));
        } finally {
            DB::rollBack();
        }
    }

    public function test_duplicate_effective_date_of_same_source_is_rejected(): void
    {
        DB::beginTransaction();
        try {
            $material = Material::where('status', 1)->firstOrFail();
            $service = app(StandardCostService::class);
            $service->setVersion($material->id, 10.0, '2026-02-01', null, 'manual');

            $this->expectException(\DomainException::class);
            $service->setVersion($material->id, 11.0, '2026-02-01', null, 'manual');
        } finally {
            DB::rollBack();
        }
    }

    public function test_business_role_forbidden_and_accountant_allowed_on_cost_page(): void
    {
        $this->actingAsDemo('sales');
        $this->get(route('finance.standard-costs'))->assertStatus(403);

        $this->actingAsDemo('accountant');
        $this->get(route('finance.standard-costs'))->assertStatus(200);
        $this->get(route('finance.cost-variance'))->assertStatus(200);

        // 总经理只读查看（view，不含 manage）
        $this->actingAsDemo('gm');
        $this->get(route('finance.cost-variance'))->assertStatus(200);
    }

    public function test_purchase_receipt_writes_price_variance(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('wh-receiver');
            [$material, $stock] = $this->makeMaterialWithStock('CV', 0);
            $materialId = $material->id;
            $supplierId = (int) DB::table('suppliers')->value('id');

            app(StandardCostService::class)->setVersion($materialId, 10.0, '2026-01-01', null, 'manual');

            $poId = (int) DB::table('purchase_orders')->insertGetId([
                'po_no' => 'PO-CV' . substr(uniqid(), -6),
                'supplier_id' => $supplierId,
                'buyer_id' => (int) DB::table('users')->where('username', 'purchase')->value('id'),
                'order_date' => now()->toDateString(),
                'status' => 'confirmed',
                'total_amount' => 120,
                'received_qty' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('purchase_order_lines')->insert([
                'po_id' => $poId,
                'material_id' => $materialId,
                'qty' => 10,
                'received_qty' => 0,
                'unit_price' => 12,
            ]);

            app(PurchaseOrderService::class)->receive([
                'po_id' => $poId,
                'receipt_date' => now()->toDateString(),
                'warehouse_id' => $stock->warehouse_id,
                'location_id' => $stock->location_id,
            ], [
                ['material_id' => $materialId, 'lot_no' => 'CVLOT', 'qty' => 10],
            ]);

            $txn = InventoryTransaction::where('material_id', $materialId)
                ->where('txn_type', 'receipt')->latest('id')->first();

            $this->assertNotNull($txn);
            $this->assertEquals(10.0, (float) $txn->std_cost);
            $this->assertEquals(12.0, (float) $txn->actual_cost);
            $this->assertEquals(20.0, (float) $txn->price_variance, '价差 =(12-10)×10 应为 +20');
        } finally {
            DB::rollBack();
        }
    }

    public function test_shipment_settles_standard_cost_and_new_version_does_not_touch_sold_lines(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('sales');
            [$material, $stock] = $this->makeMaterialWithStock('SH', 10);
            app(StandardCostService::class)->setVersion($material->id, 10.0, '2026-01-01', null, 'manual');

            $customerId = (int) DB::table('customers')->value('id');
            $order = SalesOrder::create([
                'order_no' => 'SO-SH' . substr(uniqid(), -6),
                'customer_id' => $customerId,
                'order_date' => now()->toDateString(),
                'status' => 'confirmed',
                'total_amount' => 200,
                'shipped_qty' => 0,
                'salesperson_id' => (int) DB::table('users')->where('username', 'sales')->value('id'),
            ]);
            $line = SalesOrderLine::create([
                'order_id' => $order->id,
                'material_id' => $material->id,
                'qty' => 10,
                'shipped_qty' => 0,
                'unit_price' => 20,
            ]);

            $service = app(SalesOrderService::class);
            $service->ship($order->id, [$line->id => 5], ['shipment_date' => now()->toDateString()]);

            $line->refresh();
            $this->assertEquals(5.0, (float) $line->shipped_qty);
            $this->assertEquals(50.0, (float) $line->std_cost_amount, '5 件 × 标准 10 元 = 50');

            $txn = InventoryTransaction::where('material_id', $material->id)
                ->where('txn_type', 'issue')->latest('id')->first();
            $this->assertEquals(10.0, (float) $txn->std_cost);

            // 财务改价 → 旧单不变，新发货按新版本
            app(StandardCostService::class)->setVersion($material->id, 12.0, now()->toDateString(), null, 'manual');
            $service->ship($order->id, [$line->id => 5], ['shipment_date' => now()->toDateString()]);

            $line->refresh();
            $this->assertEquals(110.0, (float) $line->std_cost_amount, '首批 50（旧价）+ 第二批 60（新价）= 110');
        } finally {
            DB::rollBack();
        }
    }
}
