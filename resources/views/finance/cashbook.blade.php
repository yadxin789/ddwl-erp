@extends('layouts.admin')

@section('title', '现金日记账')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">累计收入</div><div class="fs-5 fw-bold text-success">¥{{ number_format($income, 2) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">累计支出</div><div class="fs-5 fw-bold text-danger">¥{{ number_format($expense, 2) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">净额</div><div class="fs-5 fw-bold">¥{{ number_format($income - $expense, 2) }}</div>
    </div></div></div>
</div>

@can('finance.cashbook.manage')
<div class="card mb-3"><div class="card-header">记一笔</div>
    <form method="POST" action="{{ route('finance.cashbook.store') }}" class="card-body row g-2">
        @csrf
        <div class="col-md-2"><input type="date" name="tx_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
        <div class="col-md-2">
            <select name="direction" class="form-select form-select-sm">
                <option value="in">收入</option>
                <option value="out">支出</option>
            </select>
        </div>
        <div class="col-md-2"><input type="text" name="category" class="form-control form-control-sm" required placeholder="类别（备用金/报销…）" maxlength="32"></div>
        <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" required placeholder="金额"></div>
        <div class="col-md-3"><input type="text" name="summary" class="form-control form-control-sm" placeholder="摘要" maxlength="200"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">记账</button></div>
    </form>
</div>
@endcan

<div class="card"><div class="card-header">明细（{{ $transactions->total() }}）</div>
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>日期</th><th>编号</th><th>类别</th><th>摘要</th><th class="text-end">收入</th><th class="text-end">支出</th></tr></thead>
        <tbody>
        @forelse ($transactions as $tx)
            <tr>
                <td class="small">{{ $tx->tx_date?->format('Y-m-d') }}</td>
                <td class="font-monospace small">{{ $tx->tx_no }}</td>
                <td class="small">{{ $tx->category }}</td>
                <td class="small">{{ $tx->summary }}</td>
                <td class="text-end text-success">{{ $tx->direction === 'in' ? '¥' . number_format((float) $tx->amount, 2) : '' }}</td>
                <td class="text-end text-danger">{{ $tx->direction === 'out' ? '¥' . number_format((float) $tx->amount, 2) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无流水</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $transactions->links() }}</div>
@endsection
