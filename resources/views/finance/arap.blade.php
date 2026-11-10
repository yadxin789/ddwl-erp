@extends('layouts.admin')

@section('title', '应收应付查询')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-lg-6"><div class="card"><div class="card-header">应收账龄</div><div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted">已逾期</span><span class="fw-bold text-danger">¥{{ number_format($arBucket['overdue'], 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">30 天内到期</span><span class="fw-bold">¥{{ number_format($arBucket['due30'], 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">30 天后到期</span><span class="fw-bold text-muted">¥{{ number_format($arBucket['later'], 2) }}</span></div>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">应付账龄</div><div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted">已逾期</span><span class="fw-bold text-danger">¥{{ number_format($apBucket['overdue'], 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">30 天内到期</span><span class="fw-bold">¥{{ number_format($apBucket['due30'], 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">30 天后到期</span><span class="fw-bold text-muted">¥{{ number_format($apBucket['later'], 2) }}</span></div>
    </div></div></div>
</div>

<div class="card mb-3"><div class="card-header">应收明细（未结清）</div>
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>发票号</th><th>客户</th><th>开票日</th><th>到期日</th><th class="text-end">金额</th><th class="text-end">未收</th></tr></thead>
        <tbody>
        @forelse ($ar as $row)
            <tr>
                <td class="font-monospace small">{{ $row->invoice_no }}</td>
                <td class="small">{{ $row->customer->customer_name ?? '-' }}</td>
                <td class="small">{{ $row->invoice_date?->format('Y-m-d') }}</td>
                <td class="small">{{ $row->due_date?->format('Y-m-d') ?? '-' }}</td>
                <td class="text-end">¥{{ number_format((float) $row->amount, 2) }}</td>
                <td class="text-end text-danger">¥{{ number_format((float) $row->amount - (float) $row->paid_amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">无未结清应收</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card"><div class="card-header">应付明细（未结清）</div>
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>账单号</th><th>供应商</th><th>到期日</th><th class="text-end">金额</th><th class="text-end">未付</th></tr></thead>
        <tbody>
        @forelse ($ap as $row)
            <tr>
                <td class="font-monospace small">{{ $row->bill_no }}</td>
                <td class="small">{{ $row->supplier->supplier_name ?? '-' }}</td>
                <td class="small">{{ $row->due_date?->format('Y-m-d') ?? '-' }}</td>
                <td class="text-end">¥{{ number_format((float) $row->amount, 2) }}</td>
                <td class="text-end text-danger">¥{{ number_format((float) $row->amount - (float) $row->paid_amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-4">无未结清应付</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
