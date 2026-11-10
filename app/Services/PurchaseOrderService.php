<?php

namespace App\Services;

use App\Enums\GoodsReceiptStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockStatus;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\GoodsReceiptLine;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected StandardCostService $standardCost,
        protected QualityService $quality
    ) {
    }

    public function createOrder(array $data, array $lines): PurchaseOrder
    {
        Log::info('创建采购订单', ['user_id' => Auth::id(), 'supplier_id' => $data['supplier_id'], 'lines' => count($lines)]);
        return DB::transaction(function () use ($data, $lines) {
            $po = PurchaseOrder::create([
                'po_no' => NumberGenerator::generate('purchase_order'),
                'supplier_id' => $data['supplier_id'],
                'buyer_id' => Auth::id() ?? 0,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => PurchaseOrderStatus::Draft->value,
                'total_amount' => 0,
                'remark' => $data['remark'] ?? null,
            ]);

            $totalAmount = 0;
            foreach ($lines as $line) {
                $qty = (float) $line['qty'];
                $unitPrice = (float) $line['unit_price'];
                $totalAmount += $qty * $unitPrice;

                PurchaseOrderLine::create([
                    'po_id' => $po->id,
                    'material_id' => $line['material_id'],
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'remark' => $line['remark'] ?? null,
                ]);
            }

            $po->update(['total_amount' => $totalAmount]);

            return $po->load('lines.material');
        });
    }

    public function confirm(int $poId): PurchaseOrder
    {
        Log::info('确认采购订单', ['user_id' => Auth::id(), 'po_id' => $poId]);
        return DB::transaction(function () use ($poId) {
            $po = PurchaseOrder::findOrFail($poId);

            if ($po->status !== PurchaseOrderStatus::Draft->value) {
                throw new \DomainException("采购订单状态 {$po->status} 不能确认");
            }

            $po->update(['status' => PurchaseOrderStatus::Confirmed->value]);

            return $po;
        });
    }

    /**
     * 收货：创建收货单，库存入 qc_hold 状态（待 IQC 质检）
     */
    public function receive(array $data, array $lines): GoodsReceipt
    {
        Log::info('采购收货', ['user_id' => Auth::id(), 'po_id' => $data['po_id'], 'lines' => count($lines)]);
        return DB::transaction(function () use ($data, $lines) {
            // 收货由仓管/收货员执行，单子归属采购员，必须绕开行级数据范围（授权在路由/Permission 层）
            $po = PurchaseOrder::withoutGlobalScopes()->with('lines')->findOrFail($data['po_id']);

            if (! in_array($po->status, [PurchaseOrderStatus::Confirmed->value, PurchaseOrderStatus::Received->value], true)) {
                throw new \DomainException("采购订单状态 {$po->status} 不允许收货");
            }

            $receipt = GoodsReceipt::create([
                'receipt_no' => NumberGenerator::generate('goods_receipt'),
                'po_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'receipt_date' => $data['receipt_date'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => GoodsReceiptStatus::PendingQc->value,
                'total_qty' => 0,
            ]);

            $totalQty = 0;
            $stdUnitCache = [];
            foreach ($lines as $line) {
                $qty = (float) $line['qty'];
                $totalQty += $qty;

                GoodsReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'material_id' => $line['material_id'],
                    'lot_no' => $line['lot_no'],
                    'qty' => $qty,
                    'supplier_batch' => $line['supplier_batch'] ?? null,
                ]);

                // 入库到 qc_hold 状态（待质检）
                // P1-02：采购价差 =(实际采购单价 − 标准成本) × 数量，写进库存流水，单列报表，不摊入销售成本
                $poLine = $po->lines->firstWhere('material_id', $line['material_id']);
                $actualUnit = $poLine ? (float) $poLine->unit_price : null;
                $stdUnit = $stdUnitCache[$line['material_id']]
                    ??= $this->standardCost->costAt((int) $line['material_id'], $receipt->receipt_date, false);

                $this->inventory->receipt(
                    materialId: $line['material_id'],
                    warehouseId: $data['warehouse_id'],
                    locationId: $data['location_id'] ?? 0,
                    lotNo: $line['lot_no'],
                    qty: $qty,
                    status: StockStatus::QcHold->value,
                    refType: 'goods_receipt',
                    refId: $receipt->id,
                    remark: "采购收货：{$receipt->receipt_no}",
                    stdCost: $stdUnit,
                    actualCost: $actualUnit,
                );
            }

            $receipt->update(['total_qty' => $totalQty]);

            // 更新采购订单已收货数量
            $this->refreshPoReceivedQty($po);

            return $receipt->load('lines.material');
        });
    }

    /**
     * IQC 质检：合格部分 qc_hold → available，不合格部分 qc_hold → blocked
     */
    public function inspect(int $receiptId, array $inspections): GoodsReceipt
    {
        Log::info('采购IQC质检', ['user_id' => Auth::id(), 'receipt_id' => $receiptId, 'inspections' => count($inspections)]);
        return DB::transaction(function () use ($receiptId, $inspections) {
            // P0-08：收货单归属仓库部门，质检人/采购人合法操作需绕过行级范围（授权在路由/权限层）
            $receipt = GoodsReceipt::withoutGlobalScopes()->with('lines')->findOrFail($receiptId);

            if ($receipt->status !== GoodsReceiptStatus::PendingQc->value) {
                throw new \DomainException("收货单状态 {$receipt->status} 不允许质检");
            }

            foreach ($inspections as $insp) {
                $line = $receipt->lines->firstWhere('id', $insp['line_id']);
                if (! $line) {
                    continue;
                }

                $qualifiedQty = (float) ($insp['qualified_qty'] ?? 0);
                $rejectedQty = (float) ($insp['rejected_qty'] ?? 0);

                if ($qualifiedQty + $rejectedQty > (float) $line->qty) {
                    throw new \DomainException("行 {$line->id} 合格+不合格数量超过收货数量");
                }

                // 合格品：qc_hold → available
                if ($qualifiedQty > 0) {
                    $this->inventory->changeStatus(
                        $line->material_id,
                        $receipt->warehouse_id,
                        null,
                        $line->lot_no,
                        StockStatus::QcHold->value,
                        StockStatus::Available->value,
                        $qualifiedQty,
                        "IQC合格入库：{$receipt->receipt_no}"
                    );
                }

                // 不合格品：qc_hold → blocked
                if ($rejectedQty > 0) {
                    $this->inventory->changeStatus(
                        $line->material_id,
                        $receipt->warehouse_id,
                        null,
                        $line->lot_no,
                        StockStatus::QcHold->value,
                        StockStatus::Blocked->value,
                        $rejectedQty,
                        "IQC不合格隔离：{$receipt->receipt_no}"
                    );
                }

                $line->update([
                    'qualified_qty' => $qualifiedQty,
                    'rejected_qty' => $rejectedQty,
                ]);

                // P1-06：判定落检验记录（可按收货单反查：ref_type=goods_receipt, ref_id=收货单ID）
                $this->quality->createInspection([
                    'stage' => 'IQC',
                    'material_id' => $line->material_id,
                    'ref_type' => 'goods_receipt',
                    'ref_id' => $receipt->id,
                    'sample_qty' => (float) $line->qty,
                    'qualified_qty' => $qualifiedQty,
                    'defect_qty' => $rejectedQty,
                    'remark' => "收货单 {$receipt->receipt_no} 批次 {$line->lot_no}",
                ]);
            }

            $hasRejected = $receipt->lines->sum(fn ($l) => (float) ($l->rejected_qty ?? 0)) > 0;
            $receipt->update([
                'status' => $hasRejected ? GoodsReceiptStatus::QcFailed->value : GoodsReceiptStatus::QcPassed->value,
            ]);

            // 检查采购订单是否已全部收完（P1-06：收货单 po 关系受采购行级范围影响，IQC 判定时须绕开）
            $po = $receipt->po_id
                ? PurchaseOrder::withoutGlobalScopes()->with('lines')->find($receipt->po_id)
                : null;
            if ($po) {
                $this->checkPoCompletion($po);
            }

            return $receipt->load('lines.material');
        });
    }

    public function closeOrder(int $poId): PurchaseOrder
    {
        Log::info('关闭采购订单', ['user_id' => Auth::id(), 'po_id' => $poId]);
        return DB::transaction(function () use ($poId) {
            $po = PurchaseOrder::findOrFail($poId);
            $po->update(['status' => PurchaseOrderStatus::Closed->value]);

            return $po;
        });
    }

    private function refreshPoReceivedQty(PurchaseOrder $po): void
    {
        // P0-08：收货单归属仓库部门，采购视角汇总已收数量必须绕过行级范围，否则会算成 0
        $totalReceived = GoodsReceipt::withoutGlobalScopes()->where('po_id', $po->id)
            ->whereIn('status', [
                GoodsReceiptStatus::PendingQc->value,
                GoodsReceiptStatus::QcPassed->value,
                GoodsReceiptStatus::QcFailed->value,
                GoodsReceiptStatus::Closed->value,
            ])
            ->sum('total_qty');

        $po->update([
            'received_qty' => $totalReceived,
            'status' => PurchaseOrderStatus::Received->value,
        ]);

        // 更新各行已收货量
        foreach ($po->lines as $line) {
            $lineReceived = GoodsReceiptLine::whereHas('receipt', fn ($q) => $q->withoutGlobalScopes()->where('po_id', $po->id))
                ->where('material_id', $line->material_id)
                ->sum('qty');
            $line->update(['received_qty' => $lineReceived]);
        }
    }

    private function checkPoCompletion(PurchaseOrder $po): void
    {
        $allReceived = $po->lines->every(fn ($line) => (float) $line->received_qty >= (float) $line->qty);
        if ($allReceived && $po->status === PurchaseOrderStatus::Received->value) {
            $po->update(['status' => PurchaseOrderStatus::Closed->value]);
        }
    }
}
