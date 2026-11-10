<?php

namespace App\Http\Controllers;

use App\Models\Ecom\EcomOrder;
use App\Models\Ecom\EcomPlatform;
use App\Models\Ecom\EcomReconciliationRun;
use App\Models\Ecom\PlatformInstance;
use App\Models\Event\IntegrationOutbox;
use App\Services\Ecom\EcomOrderSyncService;
use App\Services\Ecom\EcomOutboxService;
use App\Services\Ecom\EcomReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EcomController extends Controller
{
    public function platforms()
    {
        $platforms = EcomPlatform::orderBy('id')->paginate(15);

        return view('ecom.platforms', compact('platforms'));
    }

    public function platformStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:ecom_platforms,code'],
            'name' => ['required', 'string', 'max:64'],
            'shop_url' => ['nullable', 'string', 'max:256'],
        ]);
        $data['status'] = true;
        EcomPlatform::create($data);

        return back()->with('success', '平台已添加。');
    }

    public function orders(Request $request)
    {
        $instances = PlatformInstance::orderBy('name')->get();
        $selectedInstanceId = (int) ($request->input('instance_id') ?: $instances->firstWhere('status', true)?->id);

        $orders = EcomOrder::with(['platform', 'instance', 'items'])
            ->when($selectedInstanceId, fn ($q) => $q->where('platform_instance_id', $selectedInstanceId))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $kw) => $q->where('ecom_order_no', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        $outbox = IntegrationOutbox::where('event_type', 'ecom.shipment.push')
            ->whereIn('status', ['pending', 'failed', 'dead'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $reconciliation = null;
        if ($request->filled('start') && $request->filled('end')) {
            $reconciliation = EcomReconciliationRun::with('instance')
                ->when($selectedInstanceId, fn ($q) => $q->where('platform_instance_id', $selectedInstanceId))
                ->whereDate('period_start', $request->input('start'))
                ->whereDate('period_end', $request->input('end'))
                ->orderByDesc('id')->first();
        }

        return view('ecom.orders', compact('orders', 'instances', 'selectedInstanceId', 'outbox', 'reconciliation'));
    }

    /** P3-08 手动拉取订单（失败返回可读错误，不抛 500） */
    public function syncOrders(Request $request, EcomOrderSyncService $sync)
    {
        $data = $request->validate(['instance_id' => ['required', 'exists:platform_instances,id']]);
        $instance = PlatformInstance::findOrFail($data['instance_id']);

        try {
            $result = $sync->sync($instance);
        } catch (\Throwable $e) {
            return back()->withErrors(['ecom' => '订单同步异常：' . mb_substr($e->getMessage(), 0, 160)]);
        }

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['ecom' => $result['message'] ?? '订单同步失败。']);
        }

        return redirect()->route('ecom.orders', ['instance_id' => $instance->id])
            ->with('success', "「{$instance->name}」拉取完成：新增 {$result['created']} 单、更新 {$result['updated']} 单、发货待回写 {$result['queued']} 单。");
    }

    /** P3-08 手工触发对账 */
    public function reconcile(Request $request, EcomReconciliationService $reconciliation)
    {
        $data = $request->validate([
            'instance_id' => ['required', 'exists:platform_instances,id'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);
        $instance = PlatformInstance::findOrFail($data['instance_id']);

        try {
            $run = $reconciliation->run($instance, Carbon::parse($data['start']), Carbon::parse($data['end']), (int) $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['ecom' => $e->getMessage()]);
        }

        return redirect()->route('ecom.orders', [
            'instance_id' => $instance->id, 'start' => $data['start'], 'end' => $data['end'],
        ])->with('success', "对账完成：{$run->order_count} 单，金额差异 " . $run->mismatch_count . ' 单。');
    }

    /** P3-08 处理发货回写 outbox（成功/失败/死信） */
    public function dispatchOutbox(EcomOutboxService $outbox)
    {
        $result = $outbox->dispatchPending();

        return redirect()->route('ecom.orders')->with(
            'success',
            "发货回写处理 {$result['processed']} 条：成功 {$result['sent']}、失败 {$result['failed']}、转死信 {$result['dead']}。"
        );
    }

    public function retryDeadOutbox(EcomOutboxService $outbox)
    {
        $count = $outbox->retryDead();

        return redirect()->route('ecom.orders')->with('success', "已把 {$count} 条死信重置为待发送。");
    }
}
