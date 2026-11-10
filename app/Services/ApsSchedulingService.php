<?php

namespace App\Services;

use App\Models\Production\ApsPlan;
use App\Models\Production\ApsPlanEntry;
use App\Models\Production\ProductionOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * P3-2 APS：按工作中心日产能做有限产能正排。
 *
 * 口径（写入页面提示，避免误解）：
 *  - work_centers.capacity_per_day 当作"可用工时/天"（空值按 8 小时）；
 *  - routing_operations.standard_hours 当作"单件工时"（空值按 1 小时/件）；
 *  - 工单剩余数量 × 单件工时 = 该工序总工时；按工序顺序、按工单交期先后占用产能；
 *  - 每天每个工作中心占用量不超过日产能；超出排程区间的自动顺延并标记 overflow。
 */
class ApsSchedulingService
{
    public function generate(string $start, string $end, ?int $userId = null): ApsPlan
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $endDate = Carbon::parse($end)->startOfDay();
        if ($endDate->lt($startDate)) {
            throw new \DomainException('排程结束日期不能早于开始日期');
        }

        return DB::transaction(function () use ($startDate, $endDate, $start, $end, $userId) {
            $orders = ProductionOrder::withoutGlobalScopes()
                ->with(['routing.operations.workCenter'])
                ->whereIn('status', ['planned', 'released', 'in_progress'])
                ->whereNotNull('routing_id')
                ->orderBy('end_date')
                ->orderBy('id')
                ->get()
                ->filter(fn ($o) => (float) $o->plan_qty > (float) $o->completed_qty);

            if ($orders->isEmpty()) {
                throw new \DomainException('没有可排程的工单：需状态为 planned/released/in_progress、有工艺路线且未完工。');
            }

            $seq = ApsPlan::whereDate('created_at', today())->count() + 1;
            $plan = ApsPlan::create([
                'plan_no' => 'APS-' . now()->format('Ymd') . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
                'horizon_start' => $startDate->toDateString(),
                'horizon_end' => $endDate->toDateString(),
                'status' => 'draft',
                'created_by' => $userId ?? Auth::id(),
            ]);

            $capacity = [];
            $load = [];
            $overflowOrders = [];
            $operationCount = 0;
            $totalHours = 0.0;

            foreach ($orders as $order) {
                $remaining = max(0.0, (float) $order->plan_qty - (float) $order->completed_qty);
                $operationCursor = $startDate->copy();
                if ($order->start_date && Carbon::parse($order->start_date)->gt($operationCursor)) {
                    $operationCursor = Carbon::parse($order->start_date)->startOfDay();
                }

                foreach ($order->routing->operations->sortBy('operation_seq') as $op) {
                    $operationCount++;
                    $perUnit = (float) ($op->standard_hours ?? 0);
                    if ($perUnit <= 0) {
                        $perUnit = 1.0;
                    }
                    $required = $remaining * $perUnit;
                    $centerId = (int) $op->work_center_id;
                    $day = $operationCursor->copy();
                    $opStart = null;
                    $opEnd = null;

                    while ($required > 0.0001) {
                        if ($day->gt($endDate)) {
                            $overflowOrders[$order->id] = true;
                        }

                        $key = $centerId . '|' . $day->toDateString();
                        if (! isset($capacity[$key])) {
                            $capacity[$key] = (float) ($op->workCenter->capacity_per_day ?? 0);
                            if ($capacity[$key] <= 0) {
                                $capacity[$key] = 8.0;
                            }
                        }

                        if ($capacity[$key] <= 0.0001) {
                            $day = $day->addDay();
                            $operationCursor = $day->copy();
                            continue;
                        }

                        $take = min($required, $capacity[$key]);
                        ApsPlanEntry::create([
                            'plan_id' => $plan->id,
                            'production_order_id' => $order->id,
                            'operation_seq' => (int) $op->operation_seq,
                            'operation_name' => $op->operation_name,
                            'work_center_id' => $centerId,
                            'work_date' => $day->toDateString(),
                            'hours' => round($take, 2),
                        ]);

                        $capacity[$key] -= $take;
                        $required -= $take;
                        $totalHours += $take;
                        $load[$centerId] = ($load[$centerId] ?? 0) + $take;
                        $opStart = $opStart ?: $day->copy();
                        $opEnd = $day->copy();

                        if ($required > 0.0001) {
                            $day = $day->addDay();
                            $operationCursor = $day->copy();
                        }
                    }

                    $operationCursor = $opEnd ? $opEnd->copy() : $operationCursor;
                }
            }

            $days = $startDate->diffInDays($endDate) + 1;
            $centers = [];
            foreach ($load as $centerId => $hours) {
                $center = $orders->flatMap(fn ($o) => $o->routing->operations)
                    ->firstWhere('work_center_id', $centerId)?->workCenter;
                $cap = (float) ($center?->capacity_per_day ?? 0);
                if ($cap <= 0) {
                    $cap = 8.0;
                }
                $centers[] = [
                    'work_center_id' => $centerId,
                    'name' => $center?->name ?? ('#' . $centerId),
                    'hours' => round($hours, 2),
                    'capacity' => round($cap * $days, 2),
                    'utilization' => round($hours / max(0.01, $cap * $days) * 100, 1),
                ];
            }

            $summary = [
                'orders' => $orders->count(),
                'operations' => $operationCount,
                'entries' => ApsPlanEntry::where('plan_id', $plan->id)->count(),
                'total_hours' => round($totalHours, 2),
                'overflow_orders' => count($overflowOrders),
                'days' => $days,
                'centers' => $centers,
            ];
            $plan->update(['summary_json' => $summary]);

            return $plan->fresh('entries');
        });
    }
}
