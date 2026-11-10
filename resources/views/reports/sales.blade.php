@extends('layouts.admin')

@section('title', '销售分析')

@section('content')
<div class="alert alert-light border small py-2 mb-2">数据范围：{{ $scopeLabel }}</div>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">开票收入（含税）</div>
        <div class="fs-4 fw-bold">¥{{ number_format($invoicedTotal, 2) }}</div>
        <div class="text-muted small mt-1">已回款 ¥{{ number_format($invoicedPaid, 2) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">订单状态分布</div>
        <div class="fs-6 fw-bold">{{ $ordersByStatus->count() }} 种状态</div>
        <div class="text-muted small mt-1">共计 {{ $ordersByStatus->sum('cnt') }} 张订单</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">应收未收</div>
        <div class="fs-4 fw-bold text-success">¥{{ number_format(max($invoicedTotal - $invoicedPaid, 0), 2) }}</div>
    </div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card"><div class="card-header">客户贡献 TOP10（按开票额）</div>
            <table class="table table-sm mb-0"><thead class="table-light"><tr><th>客户</th><th class="text-end">开票额</th></tr></thead>
                <tbody>
                @forelse ($topCustomers as $row)
                    <tr><td>{{ $row->customer_name }}</td><td class="text-end">¥{{ number_format((float) $row->invoiced, 2) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-3">暂无开票</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header">出货数量 TOP10（按物料）</div>
            <table class="table table-sm mb-0"><thead class="table-light"><tr><th>编码</th><th>物料</th><th class="text-end">数量</th></tr></thead>
                <tbody>
                @forelse ($topProducts as $row)
                    <tr><td class="font-monospace small">{{ $row->material_code }}</td><td>{{ $row->name }}</td><td class="text-end">{{ number_format((float) $row->qty, 0) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">暂无订单明细</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mt-3"><div class="card-header">订单状态</div>
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>状态</th><th class="text-end">数量</th><th class="text-end">金额</th></tr></thead>
        <tbody>
        @foreach ($ordersByStatus as $row)
            <tr><td>{{ $row->status }}</td><td class="text-end">{{ $row->cnt }}</td><td class="text-end">¥{{ number_format((float) $row->amount, 2) }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
