@extends('layouts.admin')

@section('title', '销售订单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>销售订单（共 {{ $orders->total() }} 条）</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="GET">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="单号搜索">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            @can('sales.create')
            <a href="{{ route('sales.orders.create') }}" class="btn btn-primary btn-sm">新建订单</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>订单号</th><th>客户</th><th>订单日期</th><th>金额</th><th>已发/总量</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @foreach ($orders as $o)
                <tr>
                    <td class="font-monospace">{{ $o->order_no }}</td>
                    <td>{{ $o->customer->customer_name ?? '-' }}</td>
                    <td>{{ $o->order_date }}</td>
                    <td class="text-end">{{ number_format($o->total_amount, 2) }}</td>
                    <td>{{ rtrim(rtrim(number_format($o->shipped_qty, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($o->lines_sum_qty ?? $o->lines->sum('qty'), 2), '0'), '.') }}</td>
                    <td>
                        @php $st = ['draft'=>'草稿','confirmed'=>'已确认','picking'=>'发货中','shipped'=>'已发货','closed'=>'已关闭','cancelled'=>'已取消'][$o->status] ?? $o->status; @endphp
                        <span class="badge bg-{{ $o->status === 'draft' ? 'warning' : ($o->status === 'confirmed' ? 'info' : ($o->status === 'shipped' ? 'success' : 'secondary')) }} text-dark">{{ $st }}</span>
                    </td>
                    <td><a href="{{ route('sales.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">详情</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
