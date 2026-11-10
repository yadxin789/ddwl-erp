@extends('layouts.admin')

@section('title', '销售报价单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>销售报价单（共 {{ $quotes->total() }} 条）</span>
        <div class="d-flex gap-2">
            <form class="d-flex gap-1" method="GET">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="单号搜索">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            @can('sales.create')
            <a href="{{ route('sales.quotes.create') }}" class="btn btn-primary btn-sm">新建报价</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>报价单号</th><th>客户</th><th>报价日期</th><th>有效期</th><th>金额</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @foreach ($quotes as $q)
                <tr>
                    <td class="font-monospace">{{ $q->quote_no }}</td>
                    <td>{{ $q->customer->customer_name ?? '-' }}</td>
                    <td>{{ $q->quote_date }}</td>
                    <td>{{ $q->valid_until ?? '-' }}</td>
                    <td class="text-end">{{ number_format($q->total_amount, 2) }}</td>
                    <td>
                        <span class="badge bg-{{ $q->status === 'draft' ? 'warning' : ($q->status === 'converted' ? 'success' : 'secondary') }} text-dark">
                            {{ ['draft'=>'草稿','confirmed'=>'已确认','converted'=>'已转订单','expired'=>'已过期'][$q->status] ?? $q->status }}
                        </span>
                    </td>
                    <td>
                        @if ($q->status === 'draft')
                        <form method="POST" action="{{ route('sales.quotes.convert', $q) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">转销售订单</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $quotes->links() }}</div>
</div>
@endsection
