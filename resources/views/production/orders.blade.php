@extends('layouts.admin')

@section('title', '生产工单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>生产工单（共 {{ $orders->total() }} 条）</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="GET">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="工单号搜索">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            @can('production.create')
            <a href="{{ route('production.orders.create') }}" class="btn btn-primary btn-sm">新建工单</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>工单号</th><th>产品</th><th>计划/完工/不良</th><th>状态</th><th>计划日期</th><th>操作</th></tr>
            </thead>
            <tbody>
                @php $smap = ['planned'=>'已计划','released'=>'已下达','in_progress'=>'生产中','completed'=>'已完工','closed'=>'已关闭','cancelled'=>'已取消']; @endphp
                @forelse ($orders as $o)
                <tr>
                    <td class="font-monospace">{{ $o->order_no }}</td>
                    <td>{{ $o->product->material_code ?? '' }} {{ $o->product->name ?? '' }}</td>
                    <td>{{ rtrim(rtrim(number_format($o->plan_qty,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($o->completed_qty,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($o->defective_qty,2),'0'),'.') }}</td>
                    <td><span class="badge bg-secondary text-dark">{{ $smap[$o->status] ?? $o->status }}</span></td>
                    <td>{{ $o->start_date }} ~ {{ $o->end_date }}</td>
                    <td><a href="{{ route('production.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">详情</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">暂无生产工单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
