<?php

namespace App\Services;

use App\Enums\StockStatus;
use App\Enums\TransactionType;
use App\Models\Inventory\InventoryStatusChange;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 库存事务服务：所有库存变动必须走本服务，禁止 Controller 直接改 inventory_stock.qty
 * 每个操作在数据库事务内完成：更新库存 + 写流水（含前后值）
 * 关键行加 FOR UPDATE 锁，防并发超卖/跳号
 */
class InventoryService
{
    /**
     * 入库（收货/生产入库/退货入库等）
     *
     * @param string|null $status 入库后状态，默认 available
     */
    public function receipt(
        int $materialId,
        int $warehouseId,
        int $locationId,
        string $lotNo,
        float $qty,
        ?string $status = null,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null,
        ?float $stdCost = null,
        ?float $actualCost = null
    ): InventoryStock {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('入库数量必须大于 0');
        }
        $status = $status ?? StockStatus::Available->value;

        return DB::transaction(function () use ($materialId, $warehouseId, $locationId, $lotNo, $qty, $status, $refType, $refId, $remark, $stdCost, $actualCost) {
            // 锁定该维度库存行（不存在则创建）
            // P0-08：服务层按库存维度精确取行，不能套用"本部门仓"行级范围（授权在路由/权限层），
            // 否则生产部门账号给仓库入库时会取不到行，甚至 create 后 reload 拿到 null。
            $stock = InventoryStock::withoutGlobalScopes()->where([
                'material_id' => $materialId,
                'lot_no' => $lotNo,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'status' => $status,
            ])->lockForUpdate()->first();

            if (! $stock) {
                $stock = InventoryStock::create([
                    'material_id' => $materialId,
                    'lot_no' => $lotNo,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'status' => $status,
                    'qty' => 0,
                    'reserved_qty' => 0,
                    'receive_date' => now()->toDateString(),
                ]);
                $stock = InventoryStock::withoutGlobalScopes()->find($stock->id); // 重新载入避免 create 后部分默认值
            }

            $before = (float) $stock->qty;
            $after = $before + $qty;
            $stock->qty = $after;
            $stock->save();

            $this->recordTransaction(
                txnType: TransactionType::Receipt->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: $qty,
                qtyBefore: $before,
                qtyAfter: $after,
                toStatus: $status,
                refType: $refType,
                refId: $refId,
                remark: $remark,
                stdCost: $stdCost,
                actualCost: $actualCost,
            );

            return $stock;
        });
    }

    /**
     * 出库（发货/生产领料/报废出库等），数量不足抛异常
     *
     * @param bool $consumeReserved true=出货方即预留所属订单，同时扣减 qty 与 reserved_qty（Odoo/ERPNext 模型）
     *                              false=普通出库，只能动用未预留库存（默认）
     */
    public function issue(
        int $materialId,
        int $warehouseId,
        int $locationId,
        string $lotNo,
        float $qty,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null,
        ?string $status = null,
        bool $consumeReserved = false,
        ?float $stdCost = null,
        ?float $actualCost = null
    ): InventoryStock {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('出库数量必须大于 0');
        }
        $status = $status ?? StockStatus::Available->value;

        return DB::transaction(function () use ($materialId, $warehouseId, $locationId, $lotNo, $qty, $refType, $refId, $remark, $status, $consumeReserved, $stdCost, $actualCost) {
            $stock = $this->lockStock($materialId, $warehouseId, $locationId, $lotNo, $status);

            if ($consumeReserved) {
                // 预留所属订单出货：直接扣实际库存；同时把该批上已预留量同步扣减（不超预留量）
                if ((float) $stock->qty < $qty) {
                    throw new \DomainException("库存不足：现存 {$stock->qty} < 出库 {$qty}");
                }
            } else {
                $available = (float) $stock->qty - (float) $stock->reserved_qty;
                if ($available < $qty) {
                    throw new \DomainException("库存不足：可用 {$available} < 出库 {$qty}");
                }
            }

            $before = (float) $stock->qty;
            $after = $before - $qty;
            $stock->qty = $after;

            if ($consumeReserved && (float) $stock->reserved_qty > 0) {
                $stock->reserved_qty = max(0, (float) $stock->reserved_qty - $qty);
            }

            $stock->save();

            $this->recordTransaction(
                txnType: TransactionType::Issue->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: -$qty,
                qtyBefore: $before,
                qtyAfter: $after,
                fromStatus: $status,
                refType: $refType,
                refId: $refId,
                remark: $remark,
                stdCost: $stdCost,
                actualCost: $actualCost,
            );

            return $stock;
        });
    }

    /**
     * 移库（同物料不同库位/仓库之间转移）
     */
    public function transfer(
        int $materialId,
        int $fromWarehouseId,
        int $fromLocationId,
        int $toWarehouseId,
        int $toLocationId,
        string $lotNo,
        float $qty,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null,
        ?string $status = null
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('移库数量必须大于 0');
        }
        $status = $status ?? StockStatus::Available->value;

        DB::transaction(function () use ($materialId, $fromWarehouseId, $fromLocationId, $toWarehouseId, $toLocationId, $lotNo, $qty, $refType, $refId, $remark, $status) {
            $fromStock = $this->lockStock($materialId, $fromWarehouseId, $fromLocationId, $lotNo, $status);

            $available = (float) $fromStock->qty - (float) $fromStock->reserved_qty;
            if ($available < $qty) {
                throw new \DomainException("移库数量不足：可用 {$available} < {$qty}");
            }

            $beforeFrom = (float) $fromStock->qty;
            $fromStock->qty = $beforeFrom - $qty;
            $fromStock->save();

            // 目标行（P0-08：按维度精确取行，不套行级范围）
            $toStock = InventoryStock::withoutGlobalScopes()->where([
                'material_id' => $materialId,
                'lot_no' => $lotNo,
                'warehouse_id' => $toWarehouseId,
                'location_id' => $toLocationId,
                'status' => $status,
            ])->lockForUpdate()->first();

            if (! $toStock) {
                $toStock = InventoryStock::create([
                    'material_id' => $materialId,
                    'lot_no' => $lotNo,
                    'warehouse_id' => $toWarehouseId,
                    'location_id' => $toLocationId,
                    'status' => $status,
                    'qty' => 0,
                    'reserved_qty' => 0,
                    'receive_date' => now()->toDateString(),
                ]);
                $toStock = InventoryStock::withoutGlobalScopes()->find($toStock->id);
            }

            $beforeTo = (float) $toStock->qty;
            $toStock->qty = $beforeTo + $qty;
            $toStock->save();

            // 出库侧流水（qty_before 为源侧值，qty_after 同源侧，方向为负）
            $this->recordTransaction(
                txnType: TransactionType::Transfer->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $fromWarehouseId,
                locationId: $fromLocationId,
                qtyChange: -$qty,
                qtyBefore: $beforeFrom,
                qtyAfter: $beforeFrom - $qty,
                fromStatus: $status,
                toStatus: $status,
                refType: $refType,
                refId: $refId,
                remark: ($remark ?? '移库出') . " → 目标库位 {$toLocationId}",
            );
            // 入库侧流水
            $this->recordTransaction(
                txnType: TransactionType::Transfer->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $toWarehouseId,
                locationId: $toLocationId,
                qtyChange: $qty,
                qtyBefore: $beforeTo,
                qtyAfter: $beforeTo + $qty,
                fromStatus: $status,
                toStatus: $status,
                refType: $refType,
                refId: $refId,
                remark: ($remark ?? '移库入') . " ← 来源库位 {$fromLocationId}",
            );
        });
    }

    /**
     * 状态转换（如 待检→可用、可用→冻结 等），同维度行的状态变更
     */
    public function changeStatus(
        int $materialId,
        int $warehouseId,
        ?int $locationId,
        string $lotNo,
        string $fromStatus,
        string $toStatus,
        float $qty,
        ?string $reason = null,
        ?int $operatorId = null
    ): void {
        $locationId = $locationId ?? 0;
        if ($qty <= 0) {
            throw new \InvalidArgumentException('状态转换数量必须大于 0');
        }
        if ($fromStatus === $toStatus) {
            throw new \InvalidArgumentException('原状态与新状态不能相同');
        }

        DB::transaction(function () use ($materialId, $warehouseId, $locationId, $lotNo, $fromStatus, $toStatus, $qty, $reason, $operatorId) {
            $fromStock = $this->lockStock($materialId, $warehouseId, $locationId, $lotNo, $fromStatus);

            $available = (float) $fromStock->qty - (float) $fromStock->reserved_qty;
            if ($available < $qty) {
                throw new \DomainException("状态转换数量不足：可用 {$available} < {$qty}");
            }

            $before = (float) $fromStock->qty;
            $fromStock->qty = $before - $qty;
            $fromStock->save();

            // 目标状态行
            $toStock = InventoryStock::withoutGlobalScopes()->where([
                'material_id' => $materialId,
                'lot_no' => $lotNo,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'status' => $toStatus,
            ])->lockForUpdate()->first();

            if (! $toStock) {
                $toStock = InventoryStock::create([
                    'material_id' => $materialId,
                    'lot_no' => $lotNo,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'status' => $toStatus,
                    'qty' => 0,
                    'reserved_qty' => 0,
                    'receive_date' => now()->toDateString(),
                ]);
                $toStock = InventoryStock::withoutGlobalScopes()->find($toStock->id);
            }

            $toBefore = (float) $toStock->qty;
            $toStock->qty = $toBefore + $qty;
            $toStock->save();

            // 状态变更记录
            InventoryStatusChange::create([
                'material_id' => $materialId,
                'lot_no' => $lotNo,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'qty' => $qty,
                'reason' => $reason,
                'operator_id' => $operatorId ?? Auth::id() ?? 0,
            ]);

            // 状态转换流水（源侧）
            $this->recordTransaction(
                txnType: TransactionType::StatusChange->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: -$qty,
                qtyBefore: $before,
                qtyAfter: $before - $qty,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                remark: $reason,
            );
            // 状态转换流水（目标侧）
            $this->recordTransaction(
                txnType: TransactionType::StatusChange->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: $qty,
                qtyBefore: $toBefore,
                qtyAfter: $toBefore + $qty,
                fromStatus: $fromStatus,
                toStatus: $toStatus,
                remark: $reason,
            );
        });
    }

    /**
     * 预留库存（销售订单确认时调用）
     */
    public function reserve(
        int $materialId,
        int $warehouseId,
        int $locationId,
        string $lotNo,
        float $qty,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null
    ): InventoryStock {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('预留数量必须大于 0');
        }

        return DB::transaction(function () use ($materialId, $warehouseId, $locationId, $lotNo, $qty, $refType, $refId, $remark) {
            $stock = $this->lockStock($materialId, $warehouseId, $locationId, $lotNo, StockStatus::Available->value);

            $free = (float) $stock->qty - (float) $stock->reserved_qty;
            if ($free < $qty) {
                throw new \DomainException("可预留库存不足：可用 {$free} < 需预留 {$qty}");
            }

            $beforeReserved = (float) $stock->reserved_qty;
            $stock->reserved_qty = $beforeReserved + $qty;
            $stock->save();

            $this->recordTransaction(
                txnType: TransactionType::Reserve->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: 0,
                qtyBefore: $beforeReserved,
                qtyAfter: $beforeReserved + $qty,
                fromStatus: StockStatus::Available->value,
                toStatus: StockStatus::Reserved->value,
                refType: $refType,
                refId: $refId,
                remark: $remark,
            );

            return $stock;
        });
    }

    /**
     * 释放预留（取消订单/预留调整时调用）
     */
    public function releaseReservation(
        int $materialId,
        int $warehouseId,
        int $locationId,
        string $lotNo,
        float $qty,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null
    ): InventoryStock {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('释放数量必须大于 0');
        }

        return DB::transaction(function () use ($materialId, $warehouseId, $locationId, $lotNo, $qty, $refType, $refId, $remark) {
            $stock = $this->lockStock($materialId, $warehouseId, $locationId, $lotNo, StockStatus::Available->value);

            $reserved = (float) $stock->reserved_qty;
            if ($reserved < $qty) {
                throw new \DomainException("预留数量不足：已预留 {$reserved} < 需释放 {$qty}");
            }

            $beforeReserved = $reserved;
            $stock->reserved_qty = $beforeReserved - $qty;
            $stock->save();

            $this->recordTransaction(
                txnType: TransactionType::ReleaseReservation->value,
                materialId: $materialId,
                lotNo: $lotNo,
                warehouseId: $warehouseId,
                locationId: $locationId,
                qtyChange: 0,
                qtyBefore: $beforeReserved,
                qtyAfter: $beforeReserved - $qty,
                fromStatus: StockStatus::Reserved->value,
                toStatus: StockStatus::Available->value,
                refType: $refType,
                refId: $refId,
                remark: $remark,
            );

            return $stock;
        });
    }

    /**
     * 锁定并返回指定维度的库存行，不存在则报错
     */
    private function lockStock(int $materialId, int $warehouseId, int $locationId, string $lotNo, string $status): InventoryStock
    {
        $stock = InventoryStock::withoutGlobalScopes()->where([
            'material_id' => $materialId,
            'lot_no' => $lotNo,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'status' => $status,
        ])->lockForUpdate()->first();

        if (! $stock) {
            throw new \DomainException("库存行不存在：material={$materialId} lot={$lotNo} wh={$warehouseId} loc={$locationId} status={$status}");
        }

        return $stock;
    }

    /**
     * 写库存流水（含前后值）
     */
    private function recordTransaction(
        string $txnType,
        int $materialId,
        string $lotNo,
        int $warehouseId,
        int $locationId,
        float $qtyChange,
        float $qtyBefore,
        float $qtyAfter,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $refType = null,
        ?int $refId = null,
        ?string $remark = null,
        ?float $stdCost = null,
        ?float $actualCost = null
    ): void {
        $priceVariance = ($stdCost !== null && $actualCost !== null)
            ? round(($actualCost - $stdCost) * abs($qtyChange), 2)
            : null;

        InventoryTransaction::create([
            'material_id' => $materialId,
            'lot_no' => $lotNo,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'txn_type' => $txnType,
            'qty_change' => $qtyChange,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'std_cost' => $stdCost,
            'actual_cost' => $actualCost,
            'price_variance' => $priceVariance,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'operator_id' => Auth::id() ?? 0,
            'remark' => $remark,
            'created_at' => now(),
        ]);

        // P1-01：库存变动统一在流水写入处打日志（单一收口点，避免在 6 个 public 方法重复）
        Log::info('库存变动', [
            'txn_type' => $txnType,
            'material_id' => $materialId,
            'lot_no' => $lotNo,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'qty_change' => $qtyChange,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'operator_id' => Auth::id() ?? 0,
        ]);
    }
}
