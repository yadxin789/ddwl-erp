<?php

namespace App\Services\Ecom;

use App\Models\Ecom\EcomOrder;
use App\Models\Ecom\EcomReconciliationRun;
use App\Models\Ecom\PlatformInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** P3-08 电商对账：订单头金额 vs 订单行金额，输出差异清单与批次快照。 */
class EcomReconciliationService
{
    public function run(PlatformInstance $instance, Carbon|string $start, Carbon|string $end, ?int $userId = null): EcomReconciliationRun
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->endOfDay();
        if ($end->lt($start)) {
            throw new \DomainException('对账结束日期不能早于开始日期。');
        }

        $orders = EcomOrder::with('items')
            ->where('platform_instance_id', $instance->id)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $headerAmount = 0.0;
        $itemAmount = 0.0;
        $mismatches = [];
        $byStatus = [];
        $paidCount = 0;
        $shippedCount = 0;

        foreach ($orders as $order) {
            $header = (float) $order->total_amount;
            $items = (float) $order->items->sum(fn ($i) => (float) $i->qty * (float) $i->price);
            $headerAmount += $header;
            $itemAmount += $items;
            $byStatus[$order->status] = ($byStatus[$order->status] ?? 0) + 1;
            if (in_array($order->status, ['paid', 'shipped', 'completed'], true)) {
                $paidCount++;
            }
            if (in_array($order->status, ['shipped', 'completed'], true)) {
                $shippedCount++;
            }

            if (abs($header - $items) > 0.01 || $order->items->isEmpty()) {
                $mismatches[] = [
                    'order_no' => $order->ecom_order_no,
                    'header_amount' => round($header, 2),
                    'item_amount' => round($items, 2),
                    'diff' => round($header - $items, 2),
                    'reason' => $order->items->isEmpty() ? '无订单行' : '头行金额不一致',
                ];
            }
        }

        $mismatchCount = count($mismatches);
        $summary = [
            'by_status' => $byStatus,
            'mismatches' => array_slice($mismatches, 0, 20),
            'period' => [$start->toDateString(), $end->toDateString()],
            'instance' => $instance->name,
        ];

        return DB::transaction(fn () => EcomReconciliationRun::updateOrCreate(
            [
                'platform_instance_id' => $instance->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ],
            [
                'order_count' => $orders->count(),
                'paid_count' => $paidCount,
                'shipped_count' => $shippedCount,
                'header_amount' => round($headerAmount, 2),
                'item_amount' => round($itemAmount, 2),
                'mismatch_count' => $mismatchCount,
                'result' => $mismatchCount > 0 ? 'mismatch' : 'matched',
                'summary_json' => $summary,
                'created_by' => $userId,
            ]
        ));
    }
}
