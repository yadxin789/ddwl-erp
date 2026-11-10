<?php

namespace App\Services;

use App\Models\MasterData\Supplier;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\SupplierPerformanceScore;
use App\Support\TenancyContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P3-6 供应商绩效自动计分。
 *
 * 口径：
 *  - 准时率：收货单 receipt_date <= 采购订单 expected_date；无交期的收货单不计入分母。
 *  - 来料合格率：收货明细 qualified_qty / (qualified_qty + rejected_qty)；两值都为空的明细不计入分母。
 *  - 综合分 = 准时率 * 40% + 合格率 * 60%；某一维度无样本时按剩余权重归一化；都无样本则不计分。
 *  - 等级：A >= 90，B >= 80，C >= 70，D < 70。
 */
class SupplierPerformanceService
{
    public function generate(Carbon|string $start, Carbon|string $end, ?int $userId = null): Collection
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($end->lt($start)) {
            throw new \DomainException('统计结束日期不能早于开始日期。');
        }
        if ($start->diffInDays($end) > 366) {
            throw new \DomainException('统计区间最长 366 天。');
        }

        // 走 GoodsReceipt 的全局数据范围：HTTP 生成受当前账号范围约束，CLI 无登录时统计全量
        $receipts = GoodsReceipt::with(['purchaseOrder:id,expected_date', 'lines:id,receipt_id,qualified_qty,rejected_qty'])
            ->whereBetween('receipt_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->groupBy('supplier_id');

        $suppliers = Supplier::whereIn('id', $receipts->keys())->get()->keyBy('id');

        return DB::transaction(function () use ($receipts, $suppliers, $start, $end, $userId) {
            $saved = collect();

            foreach ($receipts as $supplierId => $rows) {
                $supplier = $suppliers->get($supplierId);
                $onTime = 0;
                $late = 0;
                $qualified = 0.0;
                $rejected = 0.0;
                $receiptDetails = [];

                foreach ($rows as $receipt) {
                    $expected = $receipt->purchaseOrder?->expected_date;
                    if ($expected) {
                        if ($receipt->receipt_date->lte($expected)) {
                            $onTime++;
                        } else {
                            $late++;
                        }
                    }

                    foreach ($receipt->lines as $line) {
                        if ($line->qualified_qty === null && $line->rejected_qty === null) {
                            continue;
                        }
                        $qualified += (float) $line->qualified_qty;
                        $rejected += (float) $line->rejected_qty;
                    }

                    $receiptDetails[] = [
                        'receipt_no' => $receipt->receipt_no,
                        'receipt_date' => $receipt->receipt_date->toDateString(),
                        'expected_date' => $expected?->toDateString(),
                        'late' => $expected ? $receipt->receipt_date->gt($expected) : null,
                        'total_qty' => (float) $receipt->total_qty,
                    ];
                }

                $eligible = $onTime + $late;
                $onTimeRate = $eligible > 0 ? round($onTime / $eligible * 100, 2) : null;
                $qualityBase = $qualified + $rejected;
                $qualityRate = $qualityBase > 0 ? round($qualified / $qualityBase * 100, 2) : null;

                $weights = [];
                if ($onTimeRate !== null) {
                    $weights['on_time'] = 0.4;
                }
                if ($qualityRate !== null) {
                    $weights['quality'] = 0.6;
                }
                $weightTotal = array_sum($weights);
                $score = null;
                if ($weightTotal > 0) {
                    $score = round(
                        (($onTimeRate ?? 0) * ($weights['on_time'] ?? 0) + ($qualityRate ?? 0) * ($weights['quality'] ?? 0)) / $weightTotal,
                        2
                    );
                }

                $grade = match (true) {
                    $score === null => null,
                    $score >= 90 => 'A',
                    $score >= 80 => 'B',
                    $score >= 70 => 'C',
                    default => 'D',
                };

                $saved->push(SupplierPerformanceScore::updateOrCreate(
                    [
                        'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                        'supplier_id' => $supplierId,
                    ],
                    [
                        'tenant_id' => $supplier?->tenant_id,
                        'supplier_code' => $supplier?->supplier_code ?? '-',
                        'supplier_name' => $supplier?->supplier_name ?? '未知供应商',
                        'receipt_count' => $rows->count(),
                        'on_time_count' => $onTime,
                        'late_count' => $late,
                        'qualified_qty' => $qualified,
                        'rejected_qty' => $rejected,
                        'on_time_rate' => $onTimeRate,
                        'quality_rate' => $qualityRate,
                        'score' => $score,
                        'grade' => $grade,
                        'detail_json' => ['receipts' => $receiptDetails],
                        'generated_by' => $userId,
                    ]
                ));
            }

            return $saved->sortByDesc(fn ($row) => $row->score ?? -1)->values();
        });
    }

    public function forPeriod(Carbon|string $start, Carbon|string $end): Collection
    {
        return SupplierPerformanceScore::whereDate('period_start', Carbon::parse($start)->toDateString())
            ->whereDate('period_end', Carbon::parse($end)->toDateString())
            ->when(app(TenancyContext::class)->id(), fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->orderByDesc('score')
            ->orderBy('supplier_name')
            ->get();
    }
}
