@extends('layouts.admin')

@section('title', '客户发票')

@section('content')
<div class="card">
    <div class="card-header">客户发票（应收）</div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.invoices.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <select name="customer_id" class="form-select form-select-sm" required>
                    @foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->customer_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="sales_order_id" class="form-select form-select-sm">
                    <option value="">不关联销售订单</option>
                    @foreach ($orders as $o)
                        <option value="{{ $o->id }}">{{ $o->order_no }}（¥{{ number_format($o->total_amount, 2) }}）</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="invoice_date" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="金额（按订单留空）"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">开具发票</button></div>
        </form>
        <div class="form-text mt-2">选择销售订单时，金额留空则按"已发货数量 × 单价"自动带出，便于发票-收款-订单三方对账。</div>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>发票号</th><th>客户</th><th>日期</th><th>金额</th><th>已收</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($invoices as $i)
            <tr>
                <td class="font-monospace">{{ $i->invoice_no }}</td>
                <td>{{ $i->customer->customer_name ?? '-' }}</td>
                <td>{{ $i->invoice_date }}</td>
                <td>¥{{ number_format($i->amount, 2) }}</td>
                <td>¥{{ number_format($i->paid_amount, 2) }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\InvoiceStatus::tryFrom($i->status)?->label() ?? $i->status }}</span></td>            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无发票</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $invoices->links() }}</div>
</div>
@endsection
