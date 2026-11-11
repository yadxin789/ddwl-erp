@extends('layouts.admin')

@section('title', '电商订单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>电商订单（多平台汇总）</span>
        <span class="small text-muted">交易闭环：拉单 → 落库 → 发货回写 → 对账</span>
    </div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-5">
                <form method="POST" action="{{ route('ecom.orders.sync') }}" class="d-flex gap-2">
                    @csrf
                    <select name="instance_id" class="form-select form-select-sm" style="max-width:280px" required>
                        <option value="">选择平台实例</option>
                        @foreach ($instances as $instance)
                            <option value="{{ $instance->id }}" @selected($selectedInstanceId === $instance->id)>
                                {{ $instance->name }}（{{ $instance->platform_key }}）{{ $instance->status ? '' : '· 已停用' }}
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-primary">拉取订单</button>
                </form>
            </div>
            <div class="col-md-7 small text-muted">
                拉取走平台适配器，按平台单号幂等落库；已发货订单自动生成发货回写任务；未配置接口地址会返回可读错误。
                @if ($instances->isEmpty())
                    <a href="{{ route('ecom.onboarding') }}">先去接入平台</a>
                @endif
            </div>
        </div>

        <form method="GET" class="d-flex gap-2">
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:200px" placeholder="平台订单号">
            <select name="instance_id" class="form-select form-select-sm" style="max-width:220px">
                <option value="">全部实例</option>
                @foreach ($instances as $instance)
                    <option value="{{ $instance->id }}" @selected($selectedInstanceId === $instance->id)>{{ $instance->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="max-width:150px">
                <option value="">全部状态</option>
                @foreach(\App\Enums\EcomOrderStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">筛选</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>平台单号</th><th>平台实例</th><th>买家</th><th>收件人</th>
                <th class="text-end">金额</th><th>状态</th><th>同步</th><th>运单</th><th>最后错误</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td class="font-monospace">{{ $o->ecom_order_no }}</td>
                    <td>{{ $o->instance->name ?? ($o->platform->name ?? '-') }}</td>
                    <td>{{ $o->buyer_nick ?? '-' }}</td>
                    <td>{{ $o->receiver_name ?? '-' }}</td>
                    <td class="text-end">¥{{ number_format($o->pay_amount, 2) }}</td>
                    <td><span class="badge bg-secondary text-dark">{{ \App\Enums\EcomOrderStatus::tryFrom($o->status)?->label() ?? $o->status }}</span></td>
                    <td>
                        @php($syncColor = ['pulled'=>'secondary','shipped'=>'warning','pushed'=>'success','error'=>'danger'][$o->sync_status] ?? 'secondary')
                        <span class="badge bg-{{ $syncColor }}">{{ $o->sync_status }}</span>
                    </td>
                    <td class="small">{{ $o->tracking_no ?: '-' }}</td>
                    <td class="small text-danger">{{ $o->last_error ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">暂无电商订单。选择一个已接入的平台点“拉取订单”。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>发货回写（Outbox）</span>
                <div class="d-flex gap-1">
                    <form method="POST" action="{{ route('ecom.outbox.dispatch') }}">@csrf<button class="btn btn-sm btn-outline-primary">处理待发/失败</button></form>
                    <form method="POST" action="{{ route('ecom.outbox.retry-dead') }}">@csrf<button class="btn btn-sm btn-outline-danger">死信重置</button></form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>单号</th><th>状态</th><th class="text-end">重试</th><th>下次重试</th><th>最后错误</th></tr></thead>
                    <tbody>
                    @forelse ($outbox as $row)
                        <tr>
                            <td class="font-monospace">{{ $row->payload['order_no'] ?? ('#' . $row->ref_id) }}</td>
                            <td>
                                @php($colors = ['pending'=>'secondary','failed'=>'warning text-dark','dead'=>'danger'])
                                <span class="badge bg-{{ $colors[$row->status] ?? 'secondary' }}">{{ \App\Support\DisplayText::status($row->status) }}</span>
                            </td>
                            <td class="text-end">{{ $row->retry_count }}/5</td>
                            <td class="small">{{ $row->next_retry_at?->format('m-d H:i') ?? '-' }}</td>
                            <td class="small text-danger">{{ $row->last_error ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">暂无待回写发货。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">订单对账</div>
            <div class="card-body">
                <form method="POST" action="{{ route('ecom.orders.reconcile') }}" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-12">
                        <select name="instance_id" class="form-select form-select-sm" required>
                            <option value="">选择平台实例</option>
                            @foreach ($instances as $instance)
                                <option value="{{ $instance->id }}" @selected($selectedInstanceId === $instance->id)>{{ $instance->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label small mb-1">开始</label>
                        <input type="date" name="start" class="form-control form-control-sm" value="{{ request('start', now()->startOfMonth()->toDateString()) }}" required>
                    </div>
                    <div class="col-5">
                        <label class="form-label small mb-1">结束</label>
                        <input type="date" name="end" class="form-control form-control-sm" value="{{ request('end', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-2"><button class="btn btn-sm btn-primary w-100">对账</button></div>
                </form>

                @if ($reconciliation)
                    @php($summary = $reconciliation->summary_json ?? [])
                    <div class="alert {{ $reconciliation->result === 'matched' ? 'alert-success' : 'alert-warning' }} py-2 small mb-2">
                        {{ $reconciliation->result === 'matched' ? '账实一致' : '存在差异' }}：
                        {{ $reconciliation->order_count }} 单（已支付 {{ $reconciliation->paid_count }}、已发货 {{ $reconciliation->shipped_count }}），
                        头金额 ¥{{ number_format($reconciliation->header_amount, 2) }}，
                        行金额 ¥{{ number_format($reconciliation->item_amount, 2) }}，
                        差异 {{ $reconciliation->mismatch_count }} 单。
                    </div>
                    @if (! empty($summary['mismatches']))
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>单号</th><th class="text-end">头</th><th class="text-end">行</th><th>原因</th></tr></thead>
                            <tbody>
                            @foreach ($summary['mismatches'] as $m)
                                <tr>
                                    <td class="font-monospace small">{{ $m['order_no'] }}</td>
                                    <td class="text-end small">{{ number_format($m['header_amount'], 2) }}</td>
                                    <td class="text-end small">{{ number_format($m['item_amount'], 2) }}</td>
                                    <td class="small text-danger">{{ $m['reason'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                @else
                    <div class="text-muted small">按订单头金额与订单行金额（数量 × 单价）核对，差异超过 0.01 元或没有订单行即记为差异。</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection