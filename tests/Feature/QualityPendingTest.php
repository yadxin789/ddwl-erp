<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Material;
use App\Models\MasterData\Supplier;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\User;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\GoodsReceiptLine;
use App\Models\Quality\InspectionRecord;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-06 IQC 独立待检台 + 检验绑定
 * - 待检台条数 = qc_hold 库存批次数
 * - IQC 判定后写 inspection_records.ref_type=goods_receipt + ref_id
 * - 岗位锁阶段：IQC 提交 IPQC → 422；提交 IQC → 正常
 * - 记录可按收货单反查
 */
class QualityPendingTest extends TestCase
{
    private int $materialId;
    private int $warehouseId;
    private int $supplierId;
    private int $receiptId;
    private int $lineId;
    private string $lotNo = 'IQC-LOT-001';

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();

        $this->materialId = (int) Material::where('material_code', 'EL000001')->value('id');
        $this->warehouseId = (int) Warehouse::query()->value('id');
        $this->supplierId = (int) Supplier::query()->value('id');

        // 采购订单（收货单必须有 po_id）
        $po = \App\Models\Purchasing\PurchaseOrder::create([
            'po_no' => 'PO-IQC-' . substr((string) uniqid(), -6),
            'supplier_id' => $this->supplierId,
            'buyer_id' => (int) User::where('username', '赵六')->value('id'),
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
            'total_amount' => 200,
        ]);
        \App\Models\Purchasing\PurchaseOrderLine::create([
            'po_id' => $po->id,
            'material_id' => $this->materialId,
            'qty' => 100,
            'unit_price' => 2,
            'received_qty' => 0,
        ]);

        // 收货单（待检）+ 行 + qc_hold 库存（制造"1 个待检批次"）
        $this->receiptId = (int) GoodsReceipt::withoutGlobalScopes()->create([
            'receipt_no' => 'GR-IQC-' . substr((string) uniqid(), -6),
            'po_id' => $po->id,
            'supplier_id' => $this->supplierId,
            'receipt_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouseId,
            'receiver_id' => (int) User::where('username', '卢伟')->value('id'),
            'department_id' => (int) Warehouse::where('id', $this->warehouseId)->value('department_id'),
            'status' => 'pending_qc',
            'total_qty' => 100,
        ])->id;

        $this->lineId = (int) GoodsReceiptLine::create([
            'receipt_id' => $this->receiptId,
            'material_id' => $this->materialId,
            'lot_no' => $this->lotNo,
            'qty' => 100,
        ])->id;

        app(InventoryService::class)->receipt(
            materialId: $this->materialId,
            warehouseId: $this->warehouseId,
            locationId: 0,
            lotNo: $this->lotNo,
            qty: 100,
            status: \App\Enums\StockStatus::QcHold->value,
            refType: 'goods_receipt',
            refId: $this->receiptId,
            remark: 'IQC 待检批次',
        );
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_pending_board_count_matches_qc_hold_batches(): void
    {
        $this->actingAs(User::where('username', '郑一')->firstOrFail());

        $qcHoldRows = InventoryStock::withoutGlobalScopes()
            ->where('status', 'qc_hold')->where('qty', '>', 0)->count();

        $res = $this->get(route('quality.pending'));
        $res->assertOk();
        $res->assertSee('IQC 待检台');
        $res->assertSee('qc_hold 批次 ' . $qcHoldRows . ' 条');
        $res->assertSee($this->lotNo);

        // 品质主管也看得到（不锁阶段）
        $this->actingAs(User::where('username', '周九')->firstOrFail());
        $this->get(route('quality.pending'))->assertOk()->assertSee('品质主管视角');
    }

    public function test_inspection_stage_is_locked_by_role(): void
    {
        $payload = [
            'material_id' => $this->materialId,
            'sample_qty' => 10,
            'qualified_qty' => 10,
            'defect_qty' => 0,
            'remark' => '阶段锁定测试',
        ];

        $this->actingAs(User::where('username', '郑一')->firstOrFail());

        // 跨阶段：IQC 提交 IPQC → 422
        $this->post(route('quality.inspections.store'), $payload + ['stage' => 'IPQC'])
            ->assertStatus(422);

        // 本阶段：IQC → 正常写入
        $this->post(route('quality.inspections.store'), $payload + ['stage' => 'IQC'])
            ->assertRedirect(route('quality.inspections'));
        $this->assertDatabaseHas('inspection_records', [
            'stage' => 'IQC',
            'material_id' => $this->materialId,
            'remark' => '阶段锁定测试',
        ]);

        // IPQC 检验员提交 IQC → 422
        $this->actingAs(User::where('username', '邓雪')->firstOrFail());
        $this->post(route('quality.inspections.store'), $payload + ['stage' => 'IQC'])
            ->assertStatus(422);
        $this->post(route('quality.inspections.store'), $payload + ['stage' => 'IPQC'])
            ->assertRedirect(route('quality.inspections'));
    }

    public function test_iqc_decision_writes_bound_inspection_record(): void
    {
        $this->actingAs(User::where('username', '郑一')->firstOrFail());

        $this->post(route('purchase.receipts.inspect', $this->receiptId), [
            'lines' => [[
                'line_id' => $this->lineId,
                'qualified_qty' => 95,
                'rejected_qty' => 5,
            ]],
        ])->assertRedirect();

        // 记录绑定收货单
        $this->assertDatabaseHas('inspection_records', [
            'ref_type' => 'goods_receipt',
            'ref_id' => $this->receiptId,
            'stage' => 'IQC',
            'material_id' => $this->materialId,
            'result' => 'rejected',
        ]);
        $record = InspectionRecord::withoutGlobalScopes()
            ->where('ref_type', 'goods_receipt')->where('ref_id', $this->receiptId)->firstOrFail();
        $this->assertSame('95.00', (string) $record->qualified_qty);
        $this->assertSame('5.00', (string) $record->defect_qty);
        $this->assertSame((int) User::where('username', '郑一')->value('id'), (int) $record->inspector_id);

        // 可按收货单反查
        $this->get(route('quality.inspections'))
            ->assertOk()
            ->assertSee('GR#' . $this->receiptId);

        // 库存状态：95 available + 5 blocked，qc_hold 清空
        $this->assertSame(0.0, (float) InventoryStock::withoutGlobalScopes()
            ->where('material_id', $this->materialId)->where('lot_no', $this->lotNo)
            ->where('status', 'qc_hold')->sum('qty'));
    }
}

