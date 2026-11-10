@extends('layouts.admin')

@section('title', '财务流水')

@section('content')
<div class="card">
    <div class="card-header">财务流水账</div>
    <div class="card-body">
        <form method="POST" action="{{ route('fin.transactions.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="category" class="form-control form-control-sm" placeholder="类别如 销售回款" required></div>
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm">
                    <option value="debit">借（收入）</option><option value="credit">贷（支出）</option>
                </select>
            </div>
            <div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="金额" required></div>
            <div class="col-md-2"><input type="date" name="tx_date" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><input name="summary" class="form-control form-control-sm" placeholder="摘要"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">登记</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>流水号</th><th>类别</th><th>方向</th><th>金额</th><th>日期</th><th>摘要</th></tr></thead>
        <tbody>
            @forelse ($txs as $t)
            <tr>
                <td class="font-monospace">{{ $t->tx_no }}</td>
                <td>{{ $t->category }}</td>
                <td>@if($t->direction=='debit')<span class="badge bg-success">借</span>@else<span class="badge bg-danger">贷</span>@endif</td>
                <td>¥{{ number_format($t->amount,2) }}</td>
                <td>{{ $t->tx_date }}</td>
                <td class="small">{{ $t->summary }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无流水</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $txs->links() }}</div>
</div>
@endsection
