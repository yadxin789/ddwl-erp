@extends('layouts.admin')

@section('title', '产能排程（APS）')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>产能排程（有限产能正排）</span>
        @if ($plan)
            <span class="small text-muted">当前方案 {{ $plan->plan_no }} · {{ $plan->status === 'published' ? '已发布' : '草稿' }}</span>
        @endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('production.aps.schedule') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small">排程开始</label>
                <input type="date" name="horizon_start" class="form-control form-control-sm" value="{{ old('horizon_start', $plan?->horizon_start?->toDateString() ?? today()->toDateString()) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small">排程结束（最长 60 天）</label>
                <input type="date" name="horizon_end" class="form-control form-control-sm" value="{{ old('horizon_end', $plan?->horizon_end?->toDateString() ?? today()->addDays(14)->toDateString()) }}" required>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary">重新排程</button>
            </div>
            <div class="col-12 small text-muted">
                口径：工作中心「日产能」按可用工时/天计算（空按 8 小时）；工序标准工时按单件工时计算（空按 1 小时/件）；按工单交期先后占用产能，同一工作中心每天不超产能，超出区间的顺延并标记溢出。
            </div>
        </form>
        @if ($errors->any())
            <div class="alert alert-danger mt-3 mb-0 py-2 small">{{ $errors->first() }}</div>
        @endif
    </div>

    @if ($plan)
        @php $s = $plan->summary_json ?? []; @endphp
        <div class="card-body border-top">
            <div class="row g-2 text-center">
                <div class="col-6 col-md-2"><div class="border rounded py-2"><div class="h5 mb-0">{{ $s['orders'] ?? 0 }}</div><div class="small text-muted">工单</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded py-2"><div class="h5 mb-0">{{ $s['operations'] ?? 0 }}</div><div class="small text-muted">工序</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded py-2"><div class="h5 mb-0">{{ $s['total_hours'] ?? 0 }}</div><div class="small text-muted">总工时</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded py-2"><div class="h5 mb-0 {{ ($s['overflow_orders'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $s['overflow_orders'] ?? 0 }}</div><div class="small text-muted">超出区间工单</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded py-2"><div class="h5 mb-0">{{ $s['days'] ?? 0 }}</div><div class="small text-muted">排程天数</div></div></div>
            </div>
            @if (! empty($s['centers']))
                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light"><tr><th>工作中心</th><th class="text-end">占用工时</th><th class="text-end">区间可用工时</th><th class="text-end">利用率</th></tr></thead>
                        <tbody>
                        @foreach ($s['centers'] as $c)
                            <tr>
                                <td>{{ $c['name'] }}</td>
                                <td class="text-end">{{ $c['hours'] }}</td>
                                <td class="text-end">{{ $c['capacity'] }}</td>
                                <td class="text-end {{ $c['utilization'] > 90 ? 'text-danger' : '' }}">{{ $c['utilization'] }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

@if ($plan)
<div class="card mb-3">
    <div class="card-header">日负荷（工时 / 日产能）</div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0 text-nowrap">
            <thead class="table-light">
                <tr>
                    <th>工作中心</th>
                    @foreach ($dates as $d)
                        <th class="text-center">{{ $d->format('m-d') }}<br><span class="small text-muted">{{ $d->isoFormat('MMMDo') }}</span></th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @forelse ($centers as $c)
                @php $cap = (float) ($c->capacity_per_day ?: 8); @endphp
                <tr>
                    <td>{{ $c->name }}<div class="small text-muted">{{ $c->code }} · {{ $cap }}/天</div></td>
                    @foreach ($dates as $d)
                        @php
                            $key = $d->toDateString();
                            $used = $load[$c->id][$key] ?? 0;
                            $ratio = $cap > 0 ? $used / $cap : 0;
                        @endphp
                        <td class="text-center {{ $used <= 0 ? 'text-muted' : ($ratio >= 1 ? 'table-danger' : ($ratio >= 0.8 ? 'table-warning' : '')) }}">
                            {{ $used > 0 ? rtrim(rtrim(number_format($used, 2), '0'), '.') : '-' }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($dates) + 1 }}" class="text-center text-muted py-3">没有运行中的工作中心</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">工单排程窗口（{{ count($orderWindows) }} 张）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>工单号</th><th>产品</th><th>数量</th><th>开始</th><th>完成</th><th class="text-end">占用工时</th><th>工序窗口</th></tr></thead>
        <tbody>
        @forelse ($orderWindows as $w)
            <tr>
                <td class="font-monospace">{{ $w['order']?->order_no ?? '-' }}</td>
                <td>{{ $w['order']?->product?->name ?? '-' }}</td>
                <td>{{ $w['order']?->plan_qty ?? '-' }}</td>
                <td>{{ $w['start'] }}</td>
                <td>{{ $w['end'] }}</td>
                <td class="text-end">{{ $w['hours'] }}</td>
                <td class="small">
                    @foreach ($w['operations'] as $op)
                        <span class="badge text-bg-light border me-1">{{ $op['seq'] }} {{ $op['name'] }} {{ $op['start'] }}~{{ $op['end'] }}（{{ $op['hours'] }}h）</span>
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">本方案没有排程明细</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif

<div class="card">
    <div class="card-header">待排程工单（有工艺路线、未完工）</div>
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>工单号</th><th>产品</th><th>工艺路线</th><th>计划/已完工</th><th>交期</th><th>状态</th></tr></thead>
        <tbody>
        @forelse ($pending as $o)
            <tr>
                <td class="font-monospace">{{ $o->order_no }}</td>
                <td>{{ $o->product?->name ?? '-' }}</td>
                <td>{{ $o->routing?->routing_code ?? '-' }}</td>
                <td>{{ $o->plan_qty }} / {{ $o->completed_qty }}</td>
                <td>{{ $o->end_date ?? '-' }}</td>
                <td>{{ \App\Support\DisplayText::status($o->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">没有待排程工单（未建工艺路线或已完工）</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
