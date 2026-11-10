<?php

namespace App\Services;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\Production\Stocktake;
use App\Models\Production\StocktakeLine;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 库存调整两段化（P0-04 / 计划 §9.3 / D-16）
 *
 * 规则：
 *  - 金额 = |差异数量| × 单位成本；单位成本取物料 cost_price，缺失时取最近采购单价，仍无 → 强制审批
 *  - 金额 < 阈值：立即生效，单据标 auto_approved=true（仍留痕）
 *  - 金额 ≥ 阈值：单据 pending，库存不动，等仓库主管审批
 *  - 生效只写一次（重复批准幂等），流水 ref_type=adjustment、ref_id=调整单 id
 */
class InventoryAdjustmentService
{
    public function __construct(protected InventoryService $inventory)
    {
    }

    public function threshold(): float
    {
        return (float) config('erp.inventory.adjust_approval_threshold', 500);
    }

    /**
     * 单位成本：物料标准价（当前用 cost_price 占位）→ 最近采购单价 → null（强制审批）
     * P1-02 标准成本上线后，这里换成 material_standard_costs 的 active 版本。
     */
    public function unitCost(int $materialId): ?float
    {
        $cost = Material::whereKey($materialId)->value('cost_price');
        if ($cost !== null && (float) $cost > 0) {
            return (float) $cost;
        }

        try {
            $last = DB::table('purchase_order_lines')
                ->where('material_id', $materialId)
                ->orderByDesc('id')
                ->value('unit_price');
            if ($last !== null && (float) $last > 0) {
                return (float) $last;
            }
        } catch (\Throwable $e) {
            Log::warning('取最近采购单价失败：' . $e->getMessage());
        }

        return null;
    }

    /**
     * 手工库存调整申请（以"调整后数量"为准）
     * @throws \InvalidArgumentException
     */
    public function requestManual(int $stockId, float $newQty, string $reason, ?int $userId): InventoryAdjustment
    {
        $stock = InventoryStock::findOrFail($stockId);
        $before = (float) $stock->qty;
        // inventory_stock.qty 是 decimal(18,2)：调整后数量对齐到 2 位小数，避免单据与实际库存差尾数
        $newQty = round($newQty, 2);

        if ($newQty < (float) $stock->reserved_qty) {
            throw new \InvalidArgumentException(sprintf(
                '调整后数量不能低于已预留量 %s（该批已被订单/工单占用）', $stock->reserved_qty
            ));
        }

        $delta = round($newQty - $before, 2);
        if ($delta == 0.0) {
            throw new \InvalidArgumentException('调整前后数量一致，无需调整');
        }

        return $this->createAndMaybeApply([
            'source' => 'manual',
            'stock_id' => $stock->id,
            'material_id' => $stock->material_id,
            'lot_no' => $stock->lot_no,
            'warehouse_id' => $stock->warehouse_id,
            'location_id' => $stock->location_id,
            'before_qty' => $before,
            'after_qty' => $newQty,
            'delta_qty' => $delta,
        ], $reason, $userId);
    }

    /**
     * 盘点差异申请（每条差异生成一张调整单）
     */
    public function requestStocktakeLine(
        Stocktake $stocktake,
        StocktakeLine $line,
        float $actualQty,
        ?int $userId
    ): InventoryAdjustment {
        $before = (float) $line->book_qty;
        $actualQty = round($actualQty, 2);
        $delta = round($actualQty - $before, 2);

        return $this->createAndMaybeApply([
            'source' => 'stocktake',
            'stocktake_id' => $stocktake->id,
            'stocktake_line_id' => $line->id,
            'material_id' => $line->material_id,
            'lot_no' => $line->lot_no,
            'warehouse_id' => $stocktake->warehouse_id,
            'location_id' => 0,
            'before_qty' => $before,
            'after_qty' => $actualQty,
            'delta_qty' => $delta,
        ], '盘点差异：' . $stocktake->stocktake_no, $userId);
    }

    /**
     * 审批通过：生效并写流水（幂等：已生效直接返回）
     */
    public function approve(int $adjustmentId, int $userId, ?string $remark = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustmentId, $userId, $remark) {
            $adj = InventoryAdjustment::findOrFail($adjustmentId);

            if (in_array($adj->status, ['approved', 'auto_approved'], true)) {
                return $adj;   // 重复批准幂等
            }
            if ($adj->status !== 'pending') {
                throw new \DomainException('该调整单状态不可审批：' . $adj->status);
            }

            $this->apply($adj);

            $adj->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_remark' => $remark,
            ]);

            $this->syncStocktakeStatus($adj);
            $this->syncApprovalInstance($adj);

            return $adj;
        });
    }

    /**
     * 库存调整页直接审批时，同步把审批中心的实例收口，避免"两处状态不一致"。
     */
    private function syncApprovalInstance(InventoryAdjustment $adj): void
    {
        $instance = \App\Models\Approval\ApprovalInstance::where('business_type', 'stocktake.adjust')
            ->where('business_id', $adj->id)
            ->where('status', 'pending')
            ->first();

        if ($instance) {
            $instance->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    /**
     * 盘点的全部差异单据都不再 pending 时，才把盘点单收口为 adjusted
     */
    private function syncStocktakeStatus(InventoryAdjustment $adj): void
    {
        if ($adj->source !== 'stocktake' || ! $adj->stocktake_id) {
            return;
        }
        if ((float) $adj->delta_qty == 0.0) {
            return;
        }

        $remaining = InventoryAdjustment::where('stocktake_id', $adj->stocktake_id)
            ->where('status', 'pending')
            ->count();

        if ($remaining === 0) {
            Stocktake::whereKey($adj->stocktake_id)
                ->update(['status' => 'adjusted', 'finish_date' => now()]);
        }
    }

    /**
     * 审批驳回：库存不动，单据作废（幂等）
     */
    public function reject(int $adjustmentId, int $userId, string $remark): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustmentId, $userId, $remark) {
            $adj = InventoryAdjustment::findOrFail($adjustmentId);

            if ($adj->status === 'void') {
                return $adj;
            }
            if (! in_array($adj->status, ['pending'], true)) {
                throw new \DomainException('只有待审批的调整单可以驳回，当前状态：' . $adj->status);
            }

            $adj->update([
                'status' => 'void',
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_remark' => $remark,
            ]);

            return $adj;
        });
    }

    private function createAndMaybeApply(array $attrs, string $reason, ?int $userId): InventoryAdjustment
    {
        $unit = $this->unitCost((int) $attrs['material_id']);
        $amount = $unit === null ? null : round(abs((float) $attrs['delta_qty']) * $unit, 2);
        $threshold = $this->threshold();
        $requiresApproval = $amount === null || $amount >= $threshold;

        $adj = InventoryAdjustment::create($attrs + [
            'adjustment_no' => NumberGenerator::generate('inventory_adjust'),
            'status' => 'pending',
            'unit_cost' => $unit,
            'amount' => $amount,
            'threshold' => $threshold,
            'auto_approved' => false,
            'reason' => $reason,
            'requested_by' => $userId,
        ]);

        Log::info('库存调整申请', [
            'user_id' => $userId,
            'adjustment_no' => $adj->adjustment_no,
            'amount' => $amount,
            'threshold' => $threshold,
            'requires_approval' => $requiresApproval,
        ]);

        if (! $requiresApproval) {
            $this->apply($adj);
            $adj->update([
                'status' => 'auto_approved',
                'auto_approved' => true,
                'approved_by' => $userId,
                'approved_at' => now(),
                'approval_remark' => sprintf('低于阈值 %s 元，自动通过', $threshold),
            ]);
        } else {
            // P1-01：超阈值调整挂审批引擎（仓库主管单审），审批中心可见；库存仍然不动
            try {
                app(ApprovalService::class)->start('stocktake.adjust', $adj->id, $userId, ['amount' => $amount]);
            } catch (\DomainException|\InvalidArgumentException $e) {
                Log::warning('库存调整未能挂审批引擎（由库存调整页自行审批）', [
                    'adjustment_id' => $adj->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $adj->refresh();
    }

    /**
     * 把调整单作用到库存（唯一入口，保证"批准前库存不动"）
     */
    private function apply(InventoryAdjustment $adj): void
    {
        if ((float) $adj->delta_qty == 0.0) {
            return;
        }

        DB::transaction(function () use ($adj) {
            $remark = sprintf('库存调整 %s：%s', $adj->adjustment_no, $adj->reason);

            if ($adj->source === 'manual' && $adj->stock_id) {
                $stock = InventoryStock::lockForUpdate()->findOrFail($adj->stock_id);
                $before = (float) $stock->qty;
                $after = (float) $adj->after_qty;
                $stock->update(['qty' => $after]);

                InventoryTransaction::create([
                    'material_id' => $stock->material_id,
                    'lot_no' => $stock->lot_no,
                    'warehouse_id' => $stock->warehouse_id,
                    'location_id' => $stock->location_id,
                    'txn_type' => 'adjust',
                    'qty_change' => round($after - $before, 4),
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'from_status' => $stock->status,
                    'to_status' => $stock->status,
                    'ref_type' => 'adjustment',
                    'ref_id' => $adj->id,
                    'operator_id' => $adj->requested_by,
                    'remark' => $remark,
                    'created_at' => now(),
                ]);

                return;
            }

            // 盘点差异：按方向补入 / 扣减，走 InventoryService 统一维护库存行与流水
            $delta = (float) $adj->delta_qty;
            $materialId = (int) $adj->material_id;
            $warehouseId = (int) $adj->warehouse_id;
            $locationId = (int) ($adj->location_id ?: 0);
            $lotNo = (string) ($adj->lot_no ?: '');

            if ($delta > 0) {
                $this->inventory->receipt(
                    materialId: $materialId,
                    warehouseId: $warehouseId,
                    locationId: $locationId,
                    lotNo: $lotNo,
                    qty: $delta,
                    status: \App\Enums\StockStatus::Available->value,
                    refType: 'adjustment',
                    refId: $adj->id,
                    remark: $remark
                );
            } else {
                $this->inventory->issue(
                    materialId: $materialId,
                    warehouseId: $warehouseId,
                    locationId: $locationId,
                    lotNo: $lotNo,
                    qty: abs($delta),
                    refType: 'adjustment',
                    refId: $adj->id,
                    remark: $remark
                );
            }
        });
    }
}
