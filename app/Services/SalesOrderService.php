<?php

namespace App\Services;

use App\Enums\SalesOrderStatus;
use App\Enums\SalesQuoteStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockStatus;
use App\Models\Inventory\InventoryStock;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Models\Sales\SalesQuote;
use App\Models\Sales\Shipment;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 销售订单服务
 * 流程：报价 → 转销售订单 → 确认（预留库存）→ 出货（扣减库存）
 * 验收口径：订单 1000 件、可用库存 300 件 → 预留 300、需生产 700
 */
class SalesOrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected StandardCostService $standardCost
    ) {
    }

    /**
     * 新建报价单（含明细），自动单号
     */
    public function createQuote(array $data, array $lines): SalesQuote
    {
        Log::info('创建销售报价', ['user_id' => Auth::id(), 'customer_id' => $data['customer_id'], 'lines' => count($lines)]);
        return DB::transaction(function () use ($data, $lines) {
            $quote = SalesQuote::create([
                'quote_no' => NumberGenerator::generate('sales_quote'),
                'customer_id' => $data['customer_id'],
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'status' => SalesOrderStatus::Draft->value,
                'total_amount' => 0,
                'remark' => $data['remark'] ?? null,
                'created_by' => Auth::id() ?? 0,
            ]);

            $total = 0;
            foreach ($lines as $line) {
                $subtotal = $line['qty'] * $line['unit_price'];
                $total += $subtotal;
                $quote->lines()->create([
                    'material_id' => $line['material_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'delivery_days' => $line['delivery_days'] ?? null,
                    'remark' => $line['remark'] ?? null,
                ]);
            }
            $quote->update(['total_amount' => $total]);

            return $quote;
        });
    }

    /**
     * 报价转销售订单
     */
    public function convertQuote(int $quoteId): SalesOrder
    {
        Log::info('报价转销售订单', ['user_id' => Auth::id(), 'quote_id' => $quoteId]);
        return DB::transaction(function () use ($quoteId) {
            $quote = SalesQuote::with('lines')->findOrFail($quoteId);

            if ($quote->status !== SalesQuoteStatus::Draft->value) {
                throw new \DomainException("报价单状态 {$quote->status} 不能转订单");
            }

            $order = SalesOrder::create([
                'order_no' => NumberGenerator::generate('sales_order'),
                'customer_id' => $quote->customer_id,
                'quote_id' => $quote->id,
                'order_date' => now()->toDateString(),
                'status' => SalesOrderStatus::Draft->value,
                'total_amount' => $quote->total_amount,
                'salesperson_id' => Auth::id() ?? 0,
            ]);

            foreach ($quote->lines as $line) {
                $order->lines()->create([
                    'material_id' => $line->material_id,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price,
                    'remark' => $line->remark,
                ]);
            }

            $quote->update(['status' => SalesQuoteStatus::Converted->value]);

            return $order;
        });
    }

    /**
     * 直接新建销售订单
     */
    public function createOrder(array $data, array $lines): SalesOrder
    {
        Log::info('创建销售订单', ['user_id' => Auth::id(), 'customer_id' => $data['customer_id'], 'lines' => count($lines)]);
        return DB::transaction(function () use ($data, $lines) {
            $order = SalesOrder::create([
                'order_no' => NumberGenerator::generate('sales_order'),
                'customer_id' => $data['customer_id'],
                'quote_id' => $data['quote_id'] ?? null,
                'order_date' => $data['order_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'status' => SalesOrderStatus::Draft->value,
                'total_amount' => 0,
                'remark' => $data['remark'] ?? null,
                'salesperson_id' => $data['salesperson_id'] ?? (Auth::id() ?? 0),
            ]);

            $total = 0;
            foreach ($lines as $line) {
                $total += $line['qty'] * $line['unit_price'];
                $order->lines()->create([
                    'material_id' => $line['material_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'remark' => $line['remark'] ?? null,
                ]);
            }
            $order->update(['total_amount' => $total]);

            return $order;
        });
    }

    /**
     * 确认订单：draft → confirmed，按 FIFO 预留可用库存
     * 返回 ['shortage_total' => 需生产总量, 'details' => [料号 => 需生产量]]
     */
    public function confirm(int $orderId): array
    {
        Log::info('确认销售订单', ['user_id' => Auth::id(), 'order_id' => $orderId]);
        return DB::transaction(function () use ($orderId) {
            $order = SalesOrder::with('lines')->findOrFail($orderId);

            if (! in_array($order->status, [SalesOrderStatus::Draft->value, SalesOrderStatus::Confirmed->value], true)) {
                throw new \DomainException("订单状态 {$order->status} 不能确认");
            }

            $shortageTotal = 0;
            $details = [];

            foreach ($order->lines as $line) {
                // 可用库存行（FIFO：先收先发）
                $rows = InventoryStock::where('material_id', $line->material_id)
                    ->where('status', StockStatus::Available->value)
                    ->whereRaw('qty > reserved_qty')
                    ->orderBy('receive_date')->orderBy('lot_no')
                    ->lockForUpdate()
                    ->get();

                $free = (float) $rows->sum(fn ($r) => (float) $r->qty - (float) $r->reserved_qty);

                $toReserve = min((float) $line->qty, $free);
                $shortage = (float) $line->qty - $toReserve;

                // 逐行预留
                $remaining = $toReserve;
                foreach ($rows as $stock) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $rowFree = (float) $stock->qty - (float) $stock->reserved_qty;
                    if ($rowFree <= 0) {
                        continue;
                    }
                    $take = min($remaining, $rowFree);
                    $this->inventory->reserve(
                        $stock->material_id,
                        $stock->warehouse_id,
                        $stock->location_id,
                        $stock->lot_no,
                        $take,
                        'sales_order',
                        $order->id,
                        "销售订单确认预留：{$order->order_no}"
                    );
                    $remaining -= $take;
                }

                if ($shortage > 0) {
                    $shortageTotal += $shortage;
                    $details[$line->material->material_code] = $shortage;
                }
            }

            if ($order->status === SalesOrderStatus::Draft->value) {
                $order->update(['status' => SalesOrderStatus::Confirmed->value]);
            }

            return [
                'shortage_total' => $shortageTotal,
                'details' => $details,
            ];
        });
    }

    /**
     * 出货：创建出货单并扣减库存
     * $items = [order_line_id => 本次发货数量]
     * 预留→释放→出库，保证预留量同步归零
     */
    public function ship(int $orderId, array $items, array $logistics): Shipment
    {
        Log::info('销售出货', ['user_id' => Auth::id(), 'order_id' => $orderId, 'items' => count($items)]);
        return DB::transaction(function () use ($orderId, $items, $logistics) {
            $order = SalesOrder::with('lines')->findOrFail($orderId);

            if (! in_array($order->status, [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Picking->value, SalesOrderStatus::Shipped->value], true)) {
                throw new \DomainException("订单状态 {$order->status} 不能出货");
            }

            $shipment = Shipment::create([
                'shipment_no' => NumberGenerator::generate('shipment'),
                'sales_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'shipment_date' => $logistics['shipment_date'] ?? now()->toDateString(),
                'logistics_company' => $logistics['logistics_company'] ?? null,
                'tracking_no' => $logistics['tracking_no'] ?? null,
                'status' => ShipmentStatus::Shipped->value,
                'total_qty' => 0,
            ]);

            $totalShipped = 0;

            foreach ($items as $lineId => $qty) {
                $line = $order->lines->firstWhere('id', $lineId);
                if (! $line) {
                    throw new \DomainException("订单行不存在：{$lineId}");
                }
                $qty = (float) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $remain = (float) $line->qty - (float) $line->shipped_qty;
                if ($qty > $remain) {
                    throw new \DomainException("出货数量超剩余待发：行 {$line->id} 剩余 {$remain} < {$qty}");
                }

                // FIFO 取库存（不限定 qty > reserved_qty：本订单已预留的库存直接消耗）
                // 服务层绕开库存的"本部门仓"数据范围：出货授权已在路由/Permission 层判定
                $rows = InventoryStock::withoutGlobalScopes()->where('material_id', $line->material_id)
                    ->where('status', StockStatus::Available->value)
                    ->whereRaw('qty > 0')
                    ->orderBy('receive_date')->orderBy('lot_no')
                    ->lockForUpdate()
                    ->get();

                $toShip = $qty;
                // P1-02：按"发货时点 active 标准成本版本"结转；无版本的物料按规则兜底/阻断（§9.4）
                $stdUnit = $this->standardCost->costAt($line->material_id, $shipment->shipment_date);
                foreach ($rows as $stock) {
                    if ($toShip <= 0) {
                        break;
                    }
                    if ((float) $stock->qty <= 0) {
                        continue;
                    }
                    $take = min($toShip, (float) $stock->qty);

                    // Odoo/ERPNext 模型：预留所属订单出货，直接扣 qty + reserved_qty，
                    // 不再走 releaseReservation（那是给"取消订单/调整预留"用的）
                    $this->inventory->issue(
                        $stock->material_id,
                        $stock->warehouse_id,
                        $stock->location_id,
                        $stock->lot_no,
                        $take,
                        'sales_ship',
                        $shipment->id,
                        "销售出货：{$shipment->shipment_no}",
                        StockStatus::Available->value,
                        true,   // consumeReserved
                        $stdUnit
                    );

                    $toShip -= $take;
                }

                if ($toShip > 0) {
                    throw new \DomainException("库存不足：订单行 {$line->id} 缺 {$toShip}");
                }

                $line->shipped_qty += $qty;
                // P1-02：标准成本结转金额累加（分批出货按每次发货时点版本累加）
                if ($stdUnit !== null) {
                    $line->std_cost_amount = round((float) $line->std_cost_amount + $stdUnit * $qty, 2);
                }
                $line->save();
                $totalShipped += $qty;
            }

            $newShipped = (float) $order->shipped_qty + $totalShipped;
            $allShipped = $newShipped >= (float) $order->lines->sum('qty');
            $order->update([
                'shipped_qty' => $newShipped,
                'status' => $allShipped ? SalesOrderStatus::Shipped->value : SalesOrderStatus::Picking->value,
            ]);

            $shipment->update(['total_qty' => $totalShipped]);

            return $shipment;
        });
    }

    /**
     * 计算订单各物料的库存短缺量
     * @return array{shortages: array<string, float>, total: float}
     */
    public function calculateShortages(SalesOrder $order): array
    {
        $order->loadMissing(['lines.material']);

        $shortages = [];
        $total = 0.0;

        foreach ($order->lines as $line) {
            $free = (float) InventoryStock::where('material_id', $line->material_id)
                ->where('status', StockStatus::Available->value)
                ->get()
                ->sum(fn ($s) => (float) $s->qty - (float) $s->reserved_qty);
            $shortage = max(0, (float) $line->qty - $free);
            if ($shortage > 0) {
                $shortages[$line->material->material_code] = $shortage;
                $total += $shortage;
            }
        }

        return ['shortages' => $shortages, 'total' => $total];
    }
}
