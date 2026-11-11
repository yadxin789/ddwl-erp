<?php

namespace Tests\Feature;

use App\Models\Bom\BomHeader;
use App\Models\Bom\BomLine;
use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Material;
use App\Models\Organization\User;
use App\Models\Production\ProductionOrder;
use App\Models\Procurement\MrpSuggestion;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\MrpService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-08 MRP：净需求 = 毛需求 - 可用 - 在途；一键转采购申请后建议关闭。
 */
class MrpTest extends TestCase
{
    private int $fgId;
    private int $rm1Id;
    private int $rm2Id;
    private int $bomId;
    private string $fgCode;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $suffix = substr((string) uniqid(), -5);
        $this->fgCode = 'MRP-FG-' . $suffix;
        $this->fgId = (int) Material::create([
            'material_code' => $this->fgCode, 'name' => 'MRP 成品', 'spec' => 'MRP-TEST', 'package' => 'BOX',
            'unit' => 'PCS', 'category_code' => 'FG', 'rohs_status' => 1, 'cost_price' => 10, 'status' => 1,
        ])->id;
        $this->rm1Id = (int) Material::create([
            'material_code' => 'MRP-RM1-' . $suffix, 'name' => 'MRP 电阻', 'spec' => '0603', 'package' => 'SMD',
            'unit' => 'PCS', 'category_code' => 'A', 'rohs_status' => 1, 'cost_price' => 2, 'status' => 1,
        ])->id;
        $this->rm2Id = (int) Material::create([
            'material_code' => 'MRP-RM2-' . $suffix, 'name' => 'MRP 电容', 'spec' => '0402', 'package' => 'SMD',
            'unit' => 'PCS', 'category_code' => 'A', 'rohs_status' => 1, 'cost_price' => 1, 'status' => 1,
        ])->id;

        $bom = BomHeader::create([
            'bom_code' => 'BOM-MRP-' . $suffix,
            'product_material_id' => $this->fgId,
            'version' => 'V1',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
        ]);
        $this->bomId = (int) $bom->id;
        BomLine::create(['bom_header_id' => $bom->id, 'parent_material_id' => $this->fgId, 'material_id' => $this->rm1Id, 'qty_per' => 2, 'unit' => 'PCS']);
        BomLine::create(['bom_header_id' => $bom->id, 'parent_material_id' => $this->fgId, 'material_id' => $this->rm2Id, 'qty_per' => 1, 'unit' => 'PCS']);

        // 销售订单 100 未交付
        $so = SalesOrder::create([
            'order_no' => 'SO-MRP-' . $suffix, 'customer_id' => (int) DB::table('customers')->value('id'),
            'order_date' => now()->toDateString(), 'delivery_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed', 'total_amount' => 1000, 'salesperson_id' => (int) User::where('username', '钱七')->value('id'),
        ]);
        SalesOrderLine::create(['order_id' => $so->id, 'material_id' => $this->fgId, 'qty' => 100, 'shipped_qty' => 0, 'unit_price' => 10]);

        // 库存：RM1 可用 30；RM2 无
        InventoryStock::withoutGlobalScopes()->create([
            'material_id' => $this->rm1Id, 'warehouse_id' => (int) DB::table('warehouses')->value('id'),
            'location_id' => 0, 'lot_no' => '', 'qty' => 30, 'reserved_qty' => 0, 'status' => 'available',
        ]);

        // 在途：PO 40 只收 20 → 在途 20（RM1）
        $supplierId = (int) DB::table('suppliers')->value('id');
        $po = PurchaseOrder::create([
            'po_no' => 'PO-MRP-' . $suffix, 'supplier_id' => $supplierId,
            'buyer_id' => (int) User::where('username', '赵六')->value('id'),
            'order_date' => now()->toDateString(), 'status' => 'confirmed', 'total_amount' => 80,
        ]);
        PurchaseOrderLine::create(['po_id' => $po->id, 'material_id' => $this->rm1Id, 'qty' => 40, 'received_qty' => 20, 'unit_price' => 2]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_net_requirement_equals_gross_minus_available_and_transit(): void
    {
        $this->actingAs(User::where('username', '赵六')->firstOrFail());

        $count = app(MrpService::class)->run();
        $this->assertGreaterThan(0, $count);

        $rm1 = MrpSuggestion::where('material_id', $this->rm1Id)->where('status', 'open')->firstOrFail();
        $rm2 = MrpSuggestion::where('material_id', $this->rm2Id)->where('status', 'open')->firstOrFail();

        // 毛需求 100 × 2 = 200；可用 30；在途 20 → 净 150
        $this->assertSame('200.0000', (string) $rm1->gross_qty);
        $this->assertSame('30.0000', (string) $rm1->available_qty);
        $this->assertSame('20.0000', (string) $rm1->transit_qty);
        $this->assertSame('150.00', (string) $rm1->net_qty);

        // RM2 无库存无在途 → 净 100
        $this->assertSame('100.00', (string) $rm2->net_qty);
        $this->assertStringContainsString('SO:', (string) $rm1->demand_source);
        $this->assertNotNull($rm1->supplier_id, '供应商取最近采购单');
    }

    public function test_independent_work_order_adds_gross_but_linked_one_does_not_double_count(): void
    {
        $this->actingAs(User::where('username', '赵六')->firstOrFail());

        // 独立工单（无销售单）50 → RM2 毛需求 +50
        ProductionOrder::create([
            'order_no' => 'WO-MRP-' . substr((string) uniqid(), -5),
            'product_material_id' => $this->fgId,
            'bom_id' => $this->bomId,
            'plan_qty' => 50,
            'completed_qty' => 0,
            'defective_qty' => 0,
            'status' => 'planned',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'department_id' => (int) DB::table('departments')->value('id'),
            'created_by' => (int) User::where('username', '吴十')->value('id'),
        ]);

        app(MrpService::class)->run();
        $rm2 = MrpSuggestion::where('material_id', $this->rm2Id)->where('status', 'open')->firstOrFail();
        $this->assertSame('150.00', (string) $rm2->net_qty, '100（销售）+50（独立工单）');

        // 再来一张关联销售单的工单，不应重复计入
        ProductionOrder::create([
            'order_no' => 'WO-MRP-L' . substr((string) uniqid(), -4),
            'product_material_id' => $this->fgId,
            'bom_id' => $this->bomId,
            'plan_qty' => 100,
            'completed_qty' => 0,
            'defective_qty' => 0,
            'status' => 'planned',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'sales_order_id' => (int) SalesOrder::where('status', 'confirmed')->value('id'),
            'department_id' => (int) DB::table('departments')->value('id'),
            'created_by' => (int) User::where('username', '吴十')->value('id'),
        ]);
        app(MrpService::class)->run();
        $rm2b = MrpSuggestion::where('material_id', $this->rm2Id)->where('status', 'open')->firstOrFail();
        $this->assertSame('150.00', (string) $rm2b->net_qty, '关联销售单的工单不重复计入');
    }

    public function test_convert_to_purchase_requisition_closes_suggestion(): void
    {
        $this->actingAs(User::where('username', '赵六')->firstOrFail());
        app(MrpService::class)->run();

        $row = MrpSuggestion::where('material_id', $this->rm1Id)->where('status', 'open')->firstOrFail();

        $this->post(route('proc.mrp.convert', $row->id))->assertRedirect(route('proc.mrp'));

        $fresh = MrpSuggestion::findOrFail($row->id);
        $this->assertSame('converted', $fresh->status);
        $this->assertNotNull($fresh->requisition_id);
        $this->assertDatabaseHas('purchase_requisitions', ['id' => $fresh->requisition_id, 'status' => 'pending']);
        $this->assertDatabaseHas('purchase_requisition_lines', [
            'requisition_id' => $fresh->requisition_id,
            'material_id' => $this->rm1Id,
            'qty' => 150,
        ]);

        // 重复转申请被拒（不产生第二张申请）
        $before = DB::table('purchase_requisitions')->count();
        $this->post(route('proc.mrp.convert', $row->id))->assertRedirect();
        $this->assertSame($before, DB::table('purchase_requisitions')->count());
    }
}
