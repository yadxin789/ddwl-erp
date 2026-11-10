@extends('layouts.admin')

@section('title', '客户退货')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>客户退货（共 {{ $returns->total() }} 条）</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="GET">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="单号搜索">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            @can('sales.return')
            <a href="{{ route('sales.returns.create') }}" class="btn btn-primary btn-sm">新建退货单</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>退货单号</th><th>客户</th><th>原订单</th><th>数量</th><th>原因</th><th>状态</th></tr>
            </thead>
            <tbody>
                @foreach ($returns as $r)
                <tr>
                    <td class="font-monospace">{{ $r->return_no }}</td>
                    <td>{{ $r->customer->customer_name ?? '-' }}</td>
                    <td class="font-monospace">{{ $r->salesOrder->order_no ?? '-' }}</td>
                    <td>{{ rtrim(rtrim(number_format($r->total_qty, 2), '0'), '.') }}</td>
                    <td class="small">{{ $r->return_reason ?? '-' }}</td>
                    <td>
                        @php $st = ['pending'=>'待收货','received'=>'已收货','inspecting'=>'检验中','closed'=>'已关闭'][$r->status] ?? $r->status; @endphp
                        <span class="badge bg-{{ $r->status === 'pending' ? 'warning' : ($r->status === 'closed' ? 'success' : 'info') }} text-dark">{{ $st }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $returns->links() }}</div>
</div>
@endsection
