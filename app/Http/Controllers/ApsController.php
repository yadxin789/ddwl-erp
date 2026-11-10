<?php

namespace App\Http\Controllers;

use App\Models\Production\ApsPlan;
use App\Models\Production\ProductionOrder;
use App\Models\Production\WorkCenter;
use App\Services\ApsSchedulingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** P3-2 APS 产能排程看板。 */
class ApsController extends Controller
{
    public function index()
    {
        $plan = ApsPlan::with(['entries.order', 'entries.workCenter'])
            ->latest('id')
            ->first();

        $centers = WorkCenter::where('status', 'active')->orderBy('code')->get();
        $dates = [];
        $load = [];
        $orderWindows = [];

        if ($plan) {
            $cursor = $plan->horizon_start->copy();
            while ($cursor->lte($plan->horizon_end)) {
                $dates[] = $cursor->copy();
                $cursor = $cursor->addDay();
            }

            foreach ($plan->entries as $entry) {
                $dateKey = $entry->work_date->toDateString();
                $load[$entry->work_center_id][$dateKey] = ($load[$entry->work_center_id][$dateKey] ?? 0) + (float) $entry->hours;

                $orderId = $entry->production_order_id;
                if (! isset($orderWindows[$orderId])) {
                    $orderWindows[$orderId] = [
                        'order' => $entry->order,
                        'start' => $entry->work_date->toDateString(),
                        'end' => $entry->work_date->toDateString(),
                        'hours' => 0.0,
                        'operations' => [],
                    ];
                }
                $orderWindows[$orderId]['end'] = $entry->work_date->toDateString();
                $orderWindows[$orderId]['hours'] += (float) $entry->hours;
                $opKey = $entry->operation_seq . '|' . $entry->operation_name;
                if (! isset($orderWindows[$orderId]['operations'][$opKey])) {
                    $orderWindows[$orderId]['operations'][$opKey] = [
                        'seq' => $entry->operation_seq,
                        'name' => $entry->operation_name,
                        'start' => $entry->work_date->toDateString(),
                        'end' => $entry->work_date->toDateString(),
                        'hours' => 0.0,
                    ];
                }
                $orderWindows[$orderId]['operations'][$opKey]['end'] = $entry->work_date->toDateString();
                $orderWindows[$orderId]['operations'][$opKey]['hours'] += (float) $entry->hours;
            }

            ksort($orderWindows);
            foreach ($orderWindows as &$window) {
                ksort($window['operations']);
                $window['operations'] = array_values($window['operations']);
            }
            unset($window);
        }

        $pending = ProductionOrder::withoutGlobalScopes()
            ->with(['product', 'routing'])
            ->whereIn('status', ['planned', 'released', 'in_progress'])
            ->whereNotNull('routing_id')
            ->whereRaw('plan_qty > completed_qty')
            ->orderBy('end_date')
            ->limit(20)
            ->get();

        return view('production.aps', compact('plan', 'centers', 'dates', 'load', 'orderWindows', 'pending'));
    }

    public function schedule(Request $request, ApsSchedulingService $service)
    {
        $data = $request->validate([
            'horizon_start' => ['required', 'date', 'after_or_equal:today'],
            'horizon_end' => ['required', 'date', 'after_or_equal:horizon_start'],
        ]);
        if (Carbon::parse($data['horizon_end'])->gt(Carbon::parse($data['horizon_start'])->addDays(60))) {
            return back()->withErrors(['horizon_end' => '排程区间最长 60 天。'])->withInput();
        }

        try {
            $plan = $service->generate($data['horizon_start'], $data['horizon_end']);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['aps' => $e->getMessage()])->withInput();
        }

        return redirect()->route('production.aps')
            ->with('success', "排程 {$plan->plan_no} 已生成：{$plan->summary_json['orders']} 张工单、{$plan->summary_json['total_hours']} 工时。");
    }
}
