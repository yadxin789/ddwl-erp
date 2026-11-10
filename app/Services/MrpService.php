<?php

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Models\Bom\BomHeader;
use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Material;
use App\Models\Procurement\MrpSuggestion;
use App\Models\Production\ProductionOrder;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P1-08 MRP：销售未交付 + 独立工单需求 → 多层 BOM 展开（含损耗）→ 减可用/在途 → 净需求 → 采购建议。
 *
 * 口径说明：
 * - 销售订单取 confirmed/picking/shipped 且 未交数量 = qty - shipped_qty > 0
 * - 工单只取 **未关联销售订单**（sales_order_id 为空）的 planned/released/in_progress，
 *   避免"销售订单 + 它的工单"重复计算；剩余量 = plan_qty - completed_qty - defective_qty
 * - 可用 = inventory_stock.status=available 的 (qty - reserved_qty) 全公司合计（不看部门范围，MRP 是计划视角）
 * - 在途 = 采购订单 draft/confirmed 的 (qty - received_qty)
 * - 净需求 = max(0, 毛需求 - 可用 - 在途)，保留 2 位
 */
class MrpService
{
    /** @var array<int,array> 叶子物料需求聚合：material_id => [gross, required_date, sources[]] */
    private array $leaf = [];

    /** @var array<int,bool> 递归防环 */
    private array $visiting = [];

    public function run(): int
    {
        $this->leaf = [];

        $this->collectSalesDemand();
        $this->collectWorkOrderDemand();

        $available = $this->availableByMaterial();
        $transit = $this->transitByMaterial();
        $supplier = $this->supplierByMaterial();

        $rows = [];
        foreach ($this->leaf as $materialId => $agg) {
            $gross = round($agg['gross'], 4);
            $avail = round($available[$materialId] ?? 0, 4);
            $onTheWay = round($transit[$materialId] ?? 0, 4);
            $net = round(max(0, $gross - $avail - $onTheWay), 2);

            $rows[] = [
                'material_id' => $materialId,
                'supplier_id' => $supplier[$materialId] ?? null,
                'required_date' => $agg['required_date'],
                'gross_qty' => $gross,
                'available_qty' => $avail,
                'transit_qty' => $onTheWay,
                'net_qty' => $net,
                'status' => 'open',
                'demand_source' => implode('；', array_slice(array_unique($agg['sources']), 0, 5)),
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows) {
            // 重新计算只刷新"待处理"建议；已转申请/已忽略的保留审计
            MrpSuggestion::where('status', 'open')->delete();
            if ($rows) {
                MrpSuggestion::insert($rows);
            }
        });

        Log::info('MRP 重新计算', ['user_id' => Auth::id(), 'suggestions' => count($rows)]);

        return count($rows);
    }

    private function collectSalesDemand(): void
    {
        $orders = SalesOrder::withoutGlobalScopes()
            ->with(['lines' => fn ($q) => $q->withoutGlobalScopes()])
            ->whereIn('status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Picking->value, SalesOrderStatus::Shipped->value])
            ->get();

        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                $remaining = (float) $line->qty - (float) $line->shipped_qty;
                if ($remaining <= 0) {
                    continue;
                }
                $date = $order->delivery_date ? $order->delivery_date->toDateString() : now()->toDateString();
                $this->explode((int) $line->material_id, $remaining, $date, "SO:{$order->order_no}");
            }
        }
    }

    private function collectWorkOrderDemand(): void
    {
        $orders = ProductionOrder::withoutGlobalScopes()
            ->whereNull('sales_order_id')
            ->whereIn('status', [
                ProductionOrderStatus::Planned->value,
                ProductionOrderStatus::Released->value,
                ProductionOrderStatus::InProgress->value,
            ])
            ->get();

        foreach ($orders as $order) {
            $remaining = (float) $order->plan_qty - (float) $order->completed_qty - (float) $order->defective_qty;
            if ($remaining <= 0) {
                continue;
            }
            $date = $order->end_date ? $order->end_date->toDateString() : now()->toDateString();
            $this->explode((int) $order->product_material_id, $remaining, $date, "WO:{$order->order_no}");
        }
    }

    /** 递归展开：有 active BOM 就展开子件，没有就是叶子（采购件） */
    private function explode(int $materialId, float $qty, string $date, string $source, int $depth = 0): void
    {
        if ($depth > 8 || $qty <= 0) {
            return;
        }
        if (isset($this->visiting[$materialId])) {
            Log::warning('MRP BOM 存在循环引用，已截断', ['material_id' => $materialId, 'source' => $source]);

            return;
        }

        $bom = BomHeader::where('product_material_id', $materialId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (! $bom) {
            $this->addLeaf($materialId, $qty, $date, $source);

            return;
        }

        $this->visiting[$materialId] = true;
        foreach ($bom->lines()->where('is_alternative', 0)->get() as $line) {
            $childQty = $qty * (float) $line->qty_per * (1 + (float) $line->loss_rate / 100);
            $this->explode((int) $line->material_id, $childQty, $date, $source, $depth + 1);
        }
        unset($this->visiting[$materialId]);
    }

    private function addLeaf(int $materialId, float $qty, string $date, string $source): void
    {
        if (! isset($this->leaf[$materialId])) {
            $this->leaf[$materialId] = ['gross' => 0, 'required_date' => $date, 'sources' => []];
        }
        $this->leaf[$materialId]['gross'] += $qty;
        if ($date < $this->leaf[$materialId]['required_date']) {
            $this->leaf[$materialId]['required_date'] = $date;
        }
        $this->leaf[$materialId]['sources'][] = $source;
    }

    private function availableByMaterial(): array
    {
        return InventoryStock::withoutGlobalScopes()
            ->where('status', 'available')
            ->selectRaw('material_id, SUM(qty - reserved_qty) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    private function transitByMaterial(): array
    {
        $openPoIds = PurchaseOrder::withoutGlobalScopes()
            ->whereIn('status', ['draft', 'confirmed'])
            ->pluck('id');

        if ($openPoIds->isEmpty()) {
            return [];
        }

        return PurchaseOrderLine::whereIn('po_id', $openPoIds)
            ->selectRaw('material_id, SUM(qty - received_qty) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id')
            ->map(fn ($v) => (float) $v)
            ->toArray();
    }

    /** 供应商：取该物料最近一张采购订单的供应商（无则空，页面显示"未指定"） */
    private function supplierByMaterial(): array
    {
        $rows = PurchaseOrderLine::whereIn('po_id', PurchaseOrder::withoutGlobalScopes()->orderByDesc('id')->limit(200)->pluck('id'))
            ->orderByDesc('id')
            ->get(['material_id', 'po_id']);

        $map = [];
        foreach ($rows as $row) {
            if (! isset($map[$row->material_id])) {
                $map[$row->material_id] = PurchaseOrder::withoutGlobalScopes()->where('id', $row->po_id)->value('supplier_id');
            }
        }

        return $map;
    }

    public function preview(): array
    {
        return [
            'materials' => Material::orderBy('material_code')->limit(300)->get(['id', 'material_code', 'name', 'unit']),
        ];
    }
}
