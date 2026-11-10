@extends('layouts.admin')

@section('title', '我的报工')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card"><div class="card-body">
            <div class="text-muted small">今日合格数</div>
            <div class="fs-4 fw-semibold">{{ rtrim(rtrim(number_format($today->good_qty ?? 0, 2), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body">
            <div class="text-muted small">今日不良数</div>
            <div class="fs-4 fw-semibold text-danger">{{ rtrim(rtrim(number_format($today->defective_qty ?? 0, 2), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body">
            <div class="text-muted small">今日工时</div>
            <div class="fs-4 fw-semibold">{{ rtrim(rtrim(number_format($today->work_hours ?? 0, 2), '0'), '.') }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card"><div class="card-body">
            <div class="text-muted small">可报工工单</div>
            <div class="fs-4 fw-semibold">{{ $orders->count() }}</div>
        </div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">可报工工单（已下达 / 生产中）</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>工单号</th><th>产品</th><th>计划 / 完工 / 不良</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @php $omap = ['released'=>'已下达','in_progress'=>'生产中']; @endphp
                @forelse ($orders as $o)
                <tr>
                    <td class="font-monospace">{{ $o->order_no }}</td>
                    <td>{{ $o->product->material_code ?? '' }} {{ $o->product->name ?? '' }}</td>
                    <td>{{ rtrim(rtrim(number_format($o->plan_qty, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($o->completed_qty, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($o->defective_qty, 2), '0'), '.') }}</td>
                    <td><span class="badge bg-info text-dark">{{ $omap[$o->status] ?? $o->status }}</span></td>
                    <td><a href="{{ route('production.orders.show', $o) }}" class="btn btn-sm btn-primary">去报工</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-muted py-4">当前没有可报工的工单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">我的报工记录</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>报工时间</th><th>工单号</th><th>产品</th><th>工序</th><th>合格 / 不良</th><th>缺陷代码</th><th>工时</th><th>备注</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                <tr>
                    <td>{{ optional($log->reported_at)->format('Y-m-d H:i') }}</td>
                    <td class="font-monospace">
                        @if ($log->productionOrder)
                            <a href="{{ route('production.orders.show', $log->production_order_id) }}">{{ $log->productionOrder->order_no }}</a>
                        @else
                            #{{ $log->production_order_id }}
                        @endif
                    </td>
                    <td>{{ $log->productionOrder->product->name ?? '-' }}</td>
                    <td>{{ $log->operation_name }}</td>
                    <td>{{ rtrim(rtrim(number_format($log->good_qty, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($log->defective_qty, 2), '0'), '.') }}</td>
                    <td>{{ $log->defect_code ?: '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($log->work_hours, 2), '0'), '.') }}</td>
                    <td class="small text-muted">{{ $log->remark }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">还没有报工记录</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $logs->links() }}</div>
</div>
@endsection
