<?php

namespace App\Support;

use App\Models\Bom\BomHeader;
use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Material;
use App\Models\Production\ProductionOrder;
use Illuminate\Support\Facades\DB;

/**
 * P3-1 ECR 变更影响分析：在提交前评估受影响 BOM / 物料 / 在库 / 未完工工单 / 波及销售单。
 *
 * 只读快照，不占用预留；库存与工单绕过行级范围，因为影响评估要看全公司口径。
 */
class EcrImpactService
{
    public function analyze(int $bomId, array $materialIds = []): array
    {
        $bom = BomHeader::withoutGlobalScopes()->with(['product', 'lines.material'])->find($bomId);
        if (! $bom) {
            throw new \DomainException('受影响 BOM 不存在');
        }

        $ids = $materialIds ?: $bom->lines->pluck('material_id')->unique()->values()->all();
        $materials = Material::whereIn('id', $ids)->get(['id', 'material_code', 'name', 'cost_price']);

        $onHand = InventoryStock::withoutGlobalScopes()
            ->whereIn('material_id', $ids)
            ->where('status', 'available')
            ->selectRaw('material_id, SUM(qty) as qty')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id');

        $materialRows = $materials->map(function ($m) use ($onHand) {
            $qty = (float) ($onHand[$m->id] ?? 0);
            $cost = (float) ($m->cost_price ?? 0);

            return [
                'id' => $m->id,
                'code' => $m->material_code,
                'name' => $m->name,
                'on_hand' => round($qty, 4),
                'cost_price' => round($cost, 4),
                'stock_value' => round($qty * $cost, 2),
            ];
        })->all();

        $orders = ProductionOrder::withoutGlobalScopes()
            ->where('bom_id', $bom->id)
            ->whereIn('status', ['planned', 'released', 'in_progress'])
            ->get(['id', 'order_no', 'plan_qty', 'completed_qty', 'sales_order_id', 'status']);

        $affectedSo = $orders->pluck('sales_order_id')->filter()->unique()->values();

        $flags = [];
        if ($orders->count() > 0) {
            $flags[] = '存在未完工工单 ' . $orders->count() . ' 张，需明确新旧料切换时点';
        }
        if (collect($materialRows)->sum('on_hand') > 0) {
            $flags[] = '受影响物料仍有在库，需隔离旧料或先耗用';
        }
        if ($affectedSo->count() > 0) {
            $flags[] = '波及销售订单 ' . $affectedSo->count() . ' 张，需通知销售与客户';
        }

        $level = ($orders->count() > 0 || $affectedSo->count() > 0)
            ? 'high'
            : (collect($materialRows)->sum('on_hand') > 0 ? 'medium' : 'low');

        return [
            'bom' => [
                'id' => $bom->id,
                'code' => $bom->bom_code,
                'version' => $bom->version,
                'product' => $bom->product?->material_code,
                'line_count' => $bom->lines->count(),
            ],
            'materials' => $materialRows,
            'open_work_orders' => [
                'count' => $orders->count(),
                'plan_qty' => round((float) $orders->sum('plan_qty'), 4),
                'remaining_qty' => round((float) $orders->sum(fn ($o) => max(0, (float) $o->plan_qty - (float) $o->completed_qty)), 4),
                'order_nos' => $orders->pluck('order_no')->all(),
            ],
            'affected_sales_orders' => [
                'count' => $affectedSo->count(),
                'ids' => $affectedSo->all(),
            ],
            'risk_level' => $level,
            'risk_flags' => $flags,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    /** 按物料编码解析 id；未知编码抛 DomainException，避免静默丢项。 */
    public function resolveMaterialCodes(string $codes): array
    {
        $codes = array_values(array_filter(array_map('trim', explode(',', $codes))));
        if (! $codes) {
            return [];
        }

        $ids = Material::whereIn('material_code', $codes)->pluck('id')->all();
        $missing = array_diff($codes, Material::whereIn('material_code', $codes)->pluck('material_code')->all());
        if ($missing) {
            throw new \DomainException('以下物料编码不存在：' . implode('、', $missing));
        }

        return $ids;
    }
}
