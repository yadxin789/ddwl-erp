@extends('layouts.admin')

@section('title', '供应商账单')

@section('content')
<div class="card">
    <div class="card-header">供应商账单（应付）</div>
    <div class="card-body">
        <form method="POST" action="{{ route('finance.bills.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <select name="supplier_id" class="form-select form-select-sm" required>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->supplier_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="date" name="bill_date" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="金额" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">录入账单</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>账单号</th><th>供应商</th><th>日期</th><th>金额</th><th>已付</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($bills as $b)
            <tr>
                <td class="font-monospace">{{ $b->bill_no }}</td>
                <td>{{ $b->supplier->supplier_name ?? '-' }}</td>
                <td>{{ $b->bill_date }}</td>
                <td>¥{{ number_format($b->amount, 2) }}</td>
                <td>¥{{ number_format($b->paid_amount, 2) }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Support\DisplayText::status($b->status) }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无账单</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $bills->links() }}</div>
</div>
@endsection
