<?php

namespace Tests\Feature;

use App\Models\MasterData\Supplier;
use App\Models\Organization\User;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\SupplierPerformanceScore;
use App\Services\SupplierPerformanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** P3-6 供应商绩效自动计分：准时率 40% + 来料合格率 60%，同期重跑覆盖。 */
class SupplierPerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_score_combines_on_time_and_quality_rates_and_is_idempotent(): void
    {
        $buyer = User::where('username', 'purchase')->firstOrFail();
        $supplierA = Supplier::create(['supplier_code' => 'QA-SUP-A', 'supplier_name' => '绩效供应商A', 'status' => 1, 'owner_id' => $buyer->id]);
        $supplierB = Supplier::create(['supplier_code' => 'QA-SUP-B', 'supplier_name' => '绩效供应商B', 'status' => 1, 'owner_id' => $buyer->id]);

        $poA = PurchaseOrder::create(['po_no' => 'QA-PO-A', 'supplier_id' => $supplierA->id, 'buyer_id' => $buyer->id, 'order_date' => '2026-11-01', 'expected_date' => '2026-11-05', 'status' => 'confirmed']);
        $poB = PurchaseOrder::create(['po_no' => 'QA-PO-B', 'supplier_id' => $supplierB->id, 'buyer_id' => $buyer->id, 'order_date' => '2026-11-01', 'expected_date' => '2026-11-03', 'status' => 'confirmed']);

        $receiptA = GoodsReceipt::create(['receipt_no' => 'QA-GR-A', 'po_id' => $poA->id, 'supplier_id' => $supplierA->id, 'receipt_date' => '2026-11-04', 'warehouse_id' => 1, 'status' => 'qc_passed', 'total_qty' => 100, 'receiver_id' => $buyer->id]);
        $receiptB = GoodsReceipt::create(['receipt_no' => 'QA-GR-B', 'po_id' => $poB->id, 'supplier_id' => $supplierB->id, 'receipt_date' => '2026-11-08', 'warehouse_id' => 1, 'status' => 'qc_passed', 'total_qty' => 100, 'receiver_id' => $buyer->id]);

        $receiptA->lines()->create(['material_id' => 1, 'lot_no' => 'QA-LOT-A', 'qty' => 100, 'qualified_qty' => 90, 'rejected_qty' => 10]);
        $receiptB->lines()->create(['material_id' => 1, 'lot_no' => 'QA-LOT-B', 'qty' => 100, 'qualified_qty' => 80, 'rejected_qty' => 20]);

        // 无交期、无检验样本：两个维度都不计分
        $supplierC = Supplier::create(['supplier_code' => 'QA-SUP-C', 'supplier_name' => '绩效供应商C', 'status' => 1, 'owner_id' => $buyer->id]);
        $poC = PurchaseOrder::create(['po_no' => 'QA-PO-C', 'supplier_id' => $supplierC->id, 'buyer_id' => $buyer->id, 'order_date' => '2026-11-01', 'expected_date' => null, 'status' => 'confirmed']);
        $receiptC = GoodsReceipt::create(['receipt_no' => 'QA-GR-C', 'po_id' => $poC->id, 'supplier_id' => $supplierC->id, 'receipt_date' => '2026-11-06', 'warehouse_id' => 1, 'status' => 'pending_qc', 'total_qty' => 10, 'receiver_id' => $buyer->id]);
        $receiptC->lines()->create(['material_id' => 1, 'lot_no' => 'QA-LOT-C', 'qty' => 10]);

        $rows = app(SupplierPerformanceService::class)->generate('2026-11-01', '2026-11-30');

        $a = $rows->firstWhere('supplier_code', 'QA-SUP-A');
        $b = $rows->firstWhere('supplier_code', 'QA-SUP-B');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertSame(1, $a->on_time_count);
        $this->assertSame(0, $a->late_count);
        $this->assertEquals(100, (float) $a->on_time_rate);
        $this->assertEquals(90, (float) $a->quality_rate);
        $this->assertEquals(94, (float) $a->score);
        $this->assertSame('A', $a->grade);

        $this->assertSame(0, $b->on_time_count);
        $this->assertSame(1, $b->late_count);
        $this->assertEquals(0, (float) $b->on_time_rate);
        $this->assertEquals(80, (float) $b->quality_rate);
        $this->assertEquals(48, (float) $b->score);
        $this->assertSame('D', $b->grade);

        $c = $rows->firstWhere('supplier_code', 'QA-SUP-C');
        $this->assertNotNull($c);
        $this->assertNull($c->on_time_rate);
        $this->assertNull($c->quality_rate);
        $this->assertNull($c->score);
        $this->assertNull($c->grade);

        // 同期重跑：更新而不是追加
        app(SupplierPerformanceService::class)->generate('2026-11-01', '2026-11-30');
        $this->assertSame(3, SupplierPerformanceScore::where('period_start', '2026-11-01')->where('period_end', '2026-11-30')->count());    }

    public function test_supplier_report_page_generates_and_lists_scores(): void
    {
        $manager = User::where('username', 'pur-manager')->firstOrFail();
        $this->actingAs($manager)
            ->post(route('report.supplier.score'), ['start' => '2026-11-01', 'end' => '2026-11-30'])
            ->assertRedirect();

        $this->actingAs($manager)->get(route('report.supplier', ['start' => '2026-11-01', 'end' => '2026-11-30']))
            ->assertOk()
            ->assertSee('供应商绩效自动计分')
            ->assertSee('准时率 40% + 来料合格率 60%');

        $this->actingAs($manager)->post(route('report.supplier.score'), ['start' => '2026-12-01', 'end' => '2026-11-01'])
            ->assertSessionHasErrors('end');
    }
}
