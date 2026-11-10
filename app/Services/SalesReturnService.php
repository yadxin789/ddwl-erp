<?php

namespace App\Services;

use App\Enums\CustomerReturnStatus;
use App\Enums\StockStatus;
use App\Models\Sales\CustomerReturn;
use App\Models\Inventory\InventoryStock;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 客户退货服务
 *
 * P1-01（§9.2）调整：退货单创建**不再直接动库存**——先挂审批（`sales_return.approve`），
 * 销售主管通过后才把 available → returned。原来"先动库存再审批"等于审批形同虚设。
 */
class SalesReturnService
{
    public function __construct(
        protected InventoryService $inventory
    ) {
    }

    /**
     * 创建退货单（pending，未动库存）。
     * $lines = [['material_id'=>, 'lot_no'=>, 'qty'=>, 'sales_order_id'=>?]]
     */
    public function create(array $data, array $lines): CustomerReturn
    {
        Log::info('创建客户退货单', ['user_id' => Auth::id(), 'customer_id' => $data['customer_id'], 'lines' => count($lines)]);
        return DB::transaction(function () use ($data, $lines) {
            $ret = CustomerReturn::create([
                'return_no' => NumberGenerator::generate('sales_return'),
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'return_reason' => $data['return_reason'] ?? null,
                'status' => CustomerReturnStatus::Pending->value,
                'total_qty' => 0,
                'created_by' => Auth::id() ?? 0,
            ]);

            $totalQty = 0;
            foreach ($lines as $line) {
                $qty = (float) $line['qty'];
                $totalQty += $qty;

                $ret->lines()->create([
                    'material_id' => $line['material_id'],
                    'lot_no' => $line['lot_no'] ?? null,
                    'qty' => $qty,
                ]);
            }

            $ret->update(['total_qty' => $totalQty]);

            return $ret;
        });
    }

    /**
     * 审批通过后执行库存动作：available → returned（待质检）。幂等。
     */
    public function apply(int $returnId): CustomerReturn
    {
        return DB::transaction(function () use ($returnId) {
            $ret = CustomerReturn::findOrFail($returnId);
            if (in_array($ret->status, [CustomerReturnStatus::Received->value, CustomerReturnStatus::Closed->value], true)) {
                return $ret; // 已执行过，幂等
            }

            foreach ($ret->lines as $line) {
                $qty = (float) $line->qty;
                $rows = InventoryStock::where('material_id', $line->material_id)
                    ->where('status', StockStatus::Available->value)
                    ->whereRaw('qty > 0')
                    ->orderBy('receive_date')->orderBy('lot_no')
                    ->lockForUpdate()
                    ->get();

                $toReturn = $qty;
                foreach ($rows as $stock) {
                    if ($toReturn <= 0) {
                        break;
                    }
                    $available = (float) $stock->qty - (float) $stock->reserved_qty;
                    if ($available <= 0) {
                        continue;
                    }
                    $take = min($toReturn, $available);

                    $this->inventory->changeStatus(
                        $stock->material_id,
                        $stock->warehouse_id,
                        $stock->location_id,
                        $stock->lot_no,
                        StockStatus::Available->value,
                        StockStatus::Returned->value,
                        $take,
                        "客户退货：{$ret->return_no}"
                    );
                    $toReturn -= $take;
                }

                if ($toReturn > 0) {
                    throw new \DomainException("可退库存不足：物料 {$line->material_id} 缺 {$toReturn}");
                }
            }

            $ret->update(['status' => CustomerReturnStatus::Received->value]);
            Log::info('客户退货已执行库存转移', ['return_id' => $returnId]);

            return $ret;
        });
    }
}
