<?php

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\StockStatus;
use App\Models\Bom\BomHeader;
use App\Models\Inventory\InventoryStock;
use App\Models\Production\MaterialIssue;
use App\Models\Production\MaterialIssueLine;
use App\Models\Production\ProductionOperationLog;
use App\Models\Production\ProductionOrder;
use App\Models\Production\Stocktake;
use App\Models\Production\StocktakeLine;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 生产工单服务：建单→展开BOM→下达→领料→报工→完工入库
 * 所有库存变动走 InventoryService，写流水。
 */
class ProductionOrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected InventoryAdjustmentService $adjustments
    ) {
    }

    public function createOrder(array $data): ProductionOrder
    {
        Log::info('创建生产工单', ['user_id' => Auth::id(), 'product_material_id' => $data['product_material_id'], 'plan_qty' => $data['plan_qty'] ?? null]);
        return DB::transaction(function () use ($data) {
            return ProductionOrder::create([
                'order_no' => NumberGenerator::generate('production_order'),
                'product_material_id' => $data['product_material_id'],
                'bom_id' => $data['bom_id'],
                'routing_id' => $data['routing_id'] ?? null,
                'plan_qty' => (float) $data['plan_qty'],
                'completed_qty' => 0,
                'defective_qty' => 0,
                'status' => ProductionOrderStatus::Planned->value,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'remark' => $data['remark'] ?? null,
            ]);
        });
    }

    /**
     * 展开 BOM 物料需求（按计划数量 × 单位用量，含损耗）
     */
    public function bomRequirements(ProductionOrder $order): array
    {
        $bom = BomHeader::with('lines.material')->findOrFail($order->bom_id);
        $req = [];
        foreach ($bom->lines as $line) {
            $per = (float) $line->qty_per;
            $loss = (float) ($line->loss_rate ?? 0) / 100;
            $qty = round($per * (float) $order->plan_qty * (1 + $loss), 2);
            $req[] = [
                'material_id' => $line->material_id,
                'material_code' => $line->material->material_code ?? '',
                'material_name' => $line->material->name ?? '',
                'qty_per' => $per,
                'required_qty' => $qty,
            ];
        }
        return $req;
    }

    public function release(int $orderId): ProductionOrder
    {
        Log::info('下达生产工单', ['user_id' => Auth::id(), 'order_id' => $orderId]);
        return DB::transaction(function () use ($orderId) {
            $order = ProductionOrder::findOrFail($orderId);
            if ($order->status !== ProductionOrderStatus::Planned->value) {
                throw new \DomainException("工单状态 {$order->status} 不能下达");
            }
            $order->update(['status' => ProductionOrderStatus::Released->value]);
            return $order;
        });
    }

    /**
     * 领料：按工单扣减原料库存（available → issue）
     */
    public function issueMaterial(int $orderId, int $warehouseId, array $lines): MaterialIssue
    {
        Log::info('生产领料', ['user_id' => Auth::id(), 'order_id' => $orderId, 'warehouse_id' => $warehouseId, 'lines' => count($lines)]);
        return DB::transaction(function () use ($orderId, $warehouseId, $lines) {
            $order = ProductionOrder::findOrFail($orderId);
            if (! in_array($order->status, [ProductionOrderStatus::Released->value, ProductionOrderStatus::InProgress->value], true)) {
                throw new \DomainException("工单状态 {$order->status} 不能领料");
            }

            $issue = MaterialIssue::create([
                'issue_no' => NumberGenerator::generate('material_issue'),
                'production_order_id' => $order->id,
                'warehouse_id' => $warehouseId,
                'status' => 'issued',
                'issue_date' => date('Y-m-d'),
                'created_by' => Auth::id() ?? 0,
            ]);

            foreach ($lines as $line) {
                if ((float) ($line['qty'] ?? 0) <= 0) {
                    continue;
                }
                MaterialIssueLine::create([
                    'material_issue_id' => $issue->id,
                    'material_id' => $line['material_id'],
                    'lot_no' => $line['lot_no'] ?? '',
                    'qty' => (float) $line['qty'],
                ]);
                $this->inventory->issue(
                    materialId: $line['material_id'],
                    warehouseId: $warehouseId,
                    locationId: 0,
                    lotNo: $line['lot_no'] ?? '',
                    qty: (float) $line['qty'],
                    refType: 'material_issue',
                    refId: $issue->id,
                    remark: "生产领料：{$issue->issue_no} 工单 {$order->order_no}"
                );
            }

            if ($order->status === ProductionOrderStatus::Released->value) {
                $order->update(['status' => ProductionOrderStatus::InProgress->value]);
            }

            return $issue->load('lines.material');
        });
    }

    /**
     * 工序报工：记录良/不良，累计完工数量
     */
    public function report(int $orderId, array $data): ProductionOperationLog
    {
        Log::info('生产报工', ['user_id' => Auth::id(), 'order_id' => $orderId, 'good_qty' => $data['good_qty'] ?? null]);
        return DB::transaction(function () use ($orderId, $data) {
            $order = ProductionOrder::findOrFail($orderId);
            if (! in_array($order->status, [ProductionOrderStatus::Released->value, ProductionOrderStatus::InProgress->value], true)) {
                throw new \DomainException("工单状态 {$order->status} 不能报工");
            }

            $log = ProductionOperationLog::create([
                'production_order_id' => $order->id,
                'operation_name' => $data['operation_name'],
                'worker_id' => Auth::id() ?? 0,
                'good_qty' => (float) $data['good_qty'],
                'defective_qty' => (float) ($data['defective_qty'] ?? 0),
                'defect_code' => $data['defect_code'] ?? null,
                'work_hours' => $data['work_hours'] ?? null,
                'reported_at' => now(),
                'remark' => $data['remark'] ?? null,
            ]);

            $order->increment('completed_qty', (float) $data['good_qty']);
            $order->increment('defective_qty', (float) ($data['defective_qty'] ?? 0));
            $order->update(['status' => ProductionOrderStatus::InProgress->value]);

            return $log;
        });
    }

    /**
     * 完工入库：成品入 available 库存
     */
    public function complete(int $orderId, int $warehouseId): ProductionOrder
    {
        Log::info('生产完工入库', ['user_id' => Auth::id(), 'order_id' => $orderId, 'warehouse_id' => $warehouseId]);
        return DB::transaction(function () use ($orderId, $warehouseId) {
            $order = ProductionOrder::findOrFail($orderId);
            if (! in_array($order->status, [ProductionOrderStatus::InProgress->value, ProductionOrderStatus::Released->value], true)) {
                throw new \DomainException("工单状态 {$order->status} 不能完工");
            }

            $finishQty = (float) $order->completed_qty;
            if ($finishQty <= 0) {
                throw new \DomainException('尚无完工数量，无法完工入库');
            }

            $this->inventory->receipt(
                materialId: $order->product_material_id,
                warehouseId: $warehouseId,
                locationId: 0,
                lotNo: 'WO-' . $order->order_no,
                qty: $finishQty,
                status: StockStatus::Available->value,
                refType: 'production_order',
                refId: $order->id,
                remark: "生产完工入库：{$order->order_no}"
            );

            $order->update(['status' => ProductionOrderStatus::Completed->value]);
            return $order;
        });
    }

    /* ---------------- 盘点 ---------------- */

    public function createStocktake(int $warehouseId): Stocktake
    {
        Log::info('创建盘点单', ['user_id' => Auth::id(), 'warehouse_id' => $warehouseId]);
        return DB::transaction(function () use ($warehouseId) {
            $st = Stocktake::create([
                'stocktake_no' => NumberGenerator::generate('stocktake'),
                'warehouse_id' => $warehouseId,
                'status' => 'counting',
                'plan_date' => date('Y-m-d'),
                'created_by' => Auth::id() ?? 0,
            ]);

            // 把当前仓库 available 库存快照为盘点明细
            $stocks = InventoryStock::where('warehouse_id', $warehouseId)
                ->where('status', StockStatus::Available->value)
                ->get();
            foreach ($stocks as $s) {
                StocktakeLine::create([
                    'stocktake_id' => $st->id,
                    'material_id' => $s->material_id,
                    'lot_no' => $s->lot_no,
                    'book_qty' => (float) $s->qty,
                ]);
            }
            return $st->load('lines.material');
        });
    }

    public function adjustStocktake(int $stocktakeId, array $actuals): Stocktake
    {
        Log::info('盘点差异调整', ['user_id' => Auth::id(), 'stocktake_id' => $stocktakeId, 'rows' => count($actuals)]);

        return DB::transaction(function () use ($stocktakeId, $actuals) {
            $st = Stocktake::findOrFail($stocktakeId);
            if ($st->status === 'adjusted') {
                throw new \DomainException('盘点单已调整，不能重复');
            }

            $pending = 0;
            foreach ($actuals as $lineId => $actualQty) {
                $line = StocktakeLine::where('stocktake_id', $st->id)->findOrFail($lineId);
                $diff = (float) $actualQty - (float) $line->book_qty;
                $line->update(['actual_qty' => $actualQty, 'diff_qty' => $diff]);

                if ($diff == 0.0) {
                    continue;
                }

                // P0-04：差异一律走调整单；低于阈值当场生效，超阈值挂审批（库存不动）
                $adj = $this->adjustments->requestStocktakeLine($st, $line, (float) $actualQty, Auth::id());
                if ($adj->status === 'pending') {
                    $pending++;
                }
            }

            // 全部差异都自动通过才把盘点单收口；还有待审单据时保持原状态，审批通过后再收口
            if ($pending === 0) {
                $st->update(['status' => 'adjusted', 'finish_date' => now()]);
            }

            return $st->refresh();
        });
    }
}
