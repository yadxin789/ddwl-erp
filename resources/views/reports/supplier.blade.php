@extends('layouts.admin')

@section('title', '供应商绩效')

@section('content')
<div class="alert alert-light border small py-2 mb-2">数据范围：{{ $scopeLabel }}</div>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">供应商数</div><div class="fs-4 fw-bold">{{ number_format($supplierCount) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">收货单数</div><div class="fs-4 fw-bold">{{ number_format($receipts) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">累计收货数量</div><div class="fs-4 fw-bold">{{ number_format($receiptQty, 0) }}</div>
    </div></div></div>
</div>

<div class="card mt-3"><div class="card-header">供应商应付 TOP10（按账单额）</div>
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>供应商</th><th class="text-end">账单额</th></tr></thead>
        <tbody>
        @forelse ($topSuppliers as $row)
            <tr><td>{{ $row->supplier_name }}</td><td class="text-end">¥{{ number_format((float) $row->billed, 2) }}</td></tr>
        @empty
            <tr><td colspan="2" class="text-center text-muted py-3">暂无供应商账单</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
