<?php

namespace Database\Seeders;

use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialStandardCost;
use App\Services\StandardCostService;
use Illuminate\Database\Seeder;

/**
 * P1-02 / D-20：演示环境给 A 类物料写**虚拟标准价**（source=demo）。
 *
 * A 类口径（演示简化，写进页面与文档）：按"在手库存价值 = 在手数量 × 现有 cost_price"降序，
 * 累计覆盖 70% 价值的前若干物料；价值为 0 时退化为按 cost_price 降序前 10 个。
 * 虚拟价取法：cost_price > 1 → 用 cost_price；否则用最近采购价；都没有 → 固定 10.00 元。
 * **上线后由客户财务在"标准成本维护"页按实际 BOM/采购价重定价，系统不预置任何真实价。**
 */
class DemoStandardCostSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(StandardCostService::class);

        $onHand = InventoryStock::query()
            ->selectRaw('material_id, SUM(qty) as qty')
            ->where('status', 'available')
            ->groupBy('material_id')
            ->pluck('qty', 'material_id');

        $materials = Material::where('status', 1)->get();

        $ranked = $materials->map(function (Material $m) use ($onHand, $service) {
            $qty = (float) ($onHand[$m->id] ?? 0);
            $price = (float) ($m->cost_price ?? 0);
            $value = $qty * ($price > 1 ? $price : ($service->latestPurchasePrice($m->id) ?? 10.0));

            return ['material' => $m, 'value' => $value];
        })->sortByDesc('value');

        $total = (float) $ranked->sum('value');
        $picked = [];
        if ($total > 0) {
            $acc = 0.0;
            foreach ($ranked as $row) {
                $picked[] = $row['material'];
                $acc += $row['value'];
                if ($acc / $total >= 0.70) {
                    break;
                }
            }
        } else {
            $picked = $ranked->take(10)->pluck('material')->all();
        }

        $created = 0;
        foreach ($picked as $m) {
            if (MaterialStandardCost::where('material_id', $m->id)->where('status', 'active')->exists()) {
                continue;
            }
            $price = (float) ($m->cost_price ?? 0);
            if ($price <= 1) {
                $price = $service->latestPurchasePrice($m->id) ?? 10.0;
            }
            $service->setVersion(
                $m->id,
                round($price, 4),
                '2026-01-01',
                null,
                'demo',
                '演示数据：虚拟标准价，上线前由财务替换'
            );
            $created++;
        }

        $this->command?->info("A 类物料演示标准价写入 {$created} 条（source=demo，共评估 {$materials->count()} 个物料）。");
    }
}
