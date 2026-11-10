@extends('layouts.admin')

@section('title', '采购订单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>采购订单（共 {{ $orders->total() }} 条）</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="GET">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="单号搜索">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            @can('purchase.create')
            <a href="{{ route('purchase.orders.create') }}" class="btn btn-primary btn-sm">新建采购订单</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>采购单号</th><th>供应商</th><th>订单日期</th><th>预计到货</th><th>金额</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $o)
                <tr>
                    <td class="font-monospace">{{ $o->po_no }}</td>
                    <td>{{ $o->supplier->supplier_name ?? '-' }}</td>
                    <td>{{ $o->order_date }}</td>
                    <td>{{ $o->expected_date ?? '-' }}</td>
                    <td class="text-end">{{ number_format($o->total_amount, 2) }}</td>
                    <td>
                        @php $stMap = ['draft'=>'草稿','confirmed'=>'已确认','received'=>'部分收货','closed'=>'已关闭','cancelled'=>'已取消']; @endphp
                        <span class="badge bg-{{ $o->status === 'draft' ? 'warning' : ($o->status === 'confirmed' ? 'info' : ($o->status === 'closed' ? 'success' : 'secondary')) }} text-dark">{{ $stMap[$o->status] ?? $o->status }}</span>
                    </td>
                    <td><a href="{{ route('purchase.orders.show', $o) }}" class="btn btn-sm btn-outline-primary">详情</a></td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">暂无采购订单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
