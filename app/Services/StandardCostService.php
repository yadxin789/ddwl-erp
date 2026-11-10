<?php

namespace App\Services;

use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialStandardCost;
use App\Models\Purchasing\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 标准成本（P1-02 / D-14 / §9.4）
 *
 * 口径：
 * - 同一物料同一时点只有 1 个 active；改价 = 新建版本（旧版本写 effective_to 并置 superseded），禁止覆盖历史
 * - 结转取"发生时点的 active 版本"，事后改价不影响已发货单据
 * - 无 active 版本时：`cost.block_ship_without_standard`（默认 false）决定是否阻断；
 *   不阻断时按"最近采购价"临时生成 source=fallback 版本，页面标黄提示
 */
class StandardCostService
{
    public function effectiveAt(int $materialId, ?string $date = null): ?MaterialStandardCost
    {
        // 历史时点必须能取到"当时 active、现在已被替换"的版本（superseded 也参与按生效区间回查）
        return MaterialStandardCost::where('material_id', $materialId)
            ->whereIn('status', ['active', 'superseded'])
            ->effectiveAt($date)
            ->orderByDesc('effective_from')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * 取发生时点标准成本；没有就按规则兜底/阻断。返回 null 表示允许"无成本"继续（不阻断且无兜底价）。
     */
    public function costAt(int $materialId, ?string $date = null, bool $allowFallback = true): ?float
    {
        $version = $this->effectiveAt($materialId, $date);
        if ($version) {
            return (float) $version->standard_cost;
        }

        if (! $allowFallback) {
            return null;
        }

        $fallback = $this->latestPurchasePrice($materialId) ?? $this->materialCostPrice($materialId);
        if ($fallback === null) {
            if (config('erp.cost.block_ship_without_standard', false)) {
                throw new \DomainException('物料 #' . $materialId . ' 没有标准成本，出货被阻断（cost.block_ship_without_standard=true）');
            }

            return null;
        }

        if (config('erp.cost.fallback_from_purchase_price', true)) {
            $this->createFallbackVersion($materialId, $fallback);
        }

        return $fallback;
    }

    public function latestPurchasePrice(int $materialId): ?float
    {
        $price = PurchaseOrderLine::where('material_id', $materialId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', ['confirmed', 'partially_received', 'received', 'closed']))
            ->orderByDesc('id')
            ->value('unit_price');

        return $price === null ? null : (float) $price;
    }

    public function materialCostPrice(int $materialId): ?float
    {
        $price = Material::whereKey($materialId)->value('cost_price');

        return $price === null || (float) $price <= 0 ? null : (float) $price;
    }

    /**
     * 新版本（财务维护页调用）。旧 active 版本自动 superseded，effective_to = 新版本生效日前一天。
     */
    public function setVersion(
        int $materialId,
        float $standardCost,
        string $effectiveFrom,
        ?int $userId = null,
        string $source = 'manual',
        ?string $remark = null
    ): MaterialStandardCost {
        if ($standardCost < 0) {
            throw new \InvalidArgumentException('标准成本不能为负数');
        }

        return DB::transaction(function () use ($materialId, $standardCost, $effectiveFrom, $userId, $source, $remark) {
            $current = MaterialStandardCost::where('material_id', $materialId)
                ->active()
                ->orderByDesc('effective_from')
                ->first();

            if ($current && $current->effective_from->toDateString() === $effectiveFrom
                && $current->source === $source) {
                throw new \DomainException('同一生效日已有 active 版本，请改生效日或停用旧版本');
            }

            if ($current) {
                $current->update([
                    'status' => 'superseded',
                    'effective_to' => now()->parse($effectiveFrom)->subDay()->toDateString(),
                ]);
            }

            $version = (int) MaterialStandardCost::where('material_id', $materialId)->max('version') + 1;

            $row = MaterialStandardCost::create([
                'material_id' => $materialId,
                'version' => $version,
                'standard_cost' => $standardCost,
                'currency' => 'CNY',
                'effective_from' => $effectiveFrom,
                'status' => 'active',
                'source' => $source,
                'approved_by' => $userId,
                'approved_at' => now(),
                'remark' => $remark,
            ]);

            Log::info('标准成本新版本', [
                'material_id' => $materialId,
                'version' => $version,
                'standard_cost' => $standardCost,
                'effective_from' => $effectiveFrom,
                'source' => $source,
            ]);

            return $row;
        });
    }

    /**
     * 停用某版本（status=superseded，effective_to=昨天），用于财务改回旧口径。
     */
    public function deactivate(int $versionId, ?int $userId = null): MaterialStandardCost
    {
        return DB::transaction(function () use ($versionId, $userId) {
            $version = MaterialStandardCost::findOrFail($versionId);
            if ($version->status !== 'active') {
                throw new \DomainException('只有 active 版本可以停用');
            }
            $version->update([
                'status' => 'superseded',
                'effective_to' => now()->subDay()->toDateString(),
                'remark' => trim(($version->remark ? $version->remark . '；' : '') . '停用（操作人 #' . ($userId ?? 0) . '）'),
            ]);

            return $version;
        });
    }

    protected function createFallbackVersion(int $materialId, float $price): MaterialStandardCost
    {
        return MaterialStandardCost::create([
            'material_id' => $materialId,
            'version' => (int) MaterialStandardCost::where('material_id', $materialId)->max('version') + 1,
            'standard_cost' => $price,
            'currency' => 'CNY',
            'effective_from' => now()->toDateString(),
            'status' => 'active',
            'source' => 'fallback',
            'remark' => '无 active 标准成本，按最近采购价临时兜底（请财务尽快定标）',
        ]);
    }

    /**
     * 发货时点结转：写 sales_order_lines.std_cost_amount 并返回单价。
     */
    public function settleShipmentLine(int $materialId, float $qty, ?string $date = null): ?float
    {
        $unit = $this->costAt($materialId, $date);

        return $unit === null ? null : round($unit * $qty, 2);
    }
}
