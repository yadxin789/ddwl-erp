@extends('layouts.admin')

@section('title', '银行对账')

@section('content')
@can('finance.bank.reconcile')
<div class="card mb-3"><div class="card-header">新增对账</div>
    <form method="POST" action="{{ route('finance.bank-reconcile.store') }}" class="card-body row g-2">
        @csrf
        <div class="col-md-2"><input type="date" name="period" class="form-control form-control-sm" required></div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="bank">银行</option>
                <option value="cash">现金</option>
            </select>
        </div>
        <div class="col-md-2"><input type="number" step="0.01" name="book_amount" class="form-control form-control-sm" required placeholder="账面余额"></div>
        <div class="col-md-2"><input type="number" step="0.01" name="actual_amount" class="form-control form-control-sm" required placeholder="银行/实际余额"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">保存对账</button></div>
    </form>
</div>
@endcan

<div class="card"><div class="card-header">对账记录</div>
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>编号</th><th>期间</th><th>类型</th><th class="text-end">账面</th><th class="text-end">实际</th><th class="text-end">差异</th><th class="text-end">状态</th></tr></thead>
        <tbody>
        @forelse ($records as $row)
            <tr>
                <td class="font-monospace small">{{ $row->reconcile_no }}</td>
                <td class="small">{{ $row->period?->format('Y-m-d') }}</td>
                <td class="small">{{ $row->type }}</td>
                <td class="text-end">¥{{ number_format((float) $row->book_amount, 2) }}</td>
                <td class="text-end">¥{{ number_format((float) $row->actual_amount, 2) }}</td>
                <td class="text-end {{ abs((float) $row->diff_amount) < 0.01 ? '' : 'text-danger' }}">¥{{ number_format((float) $row->diff_amount, 2) }}</td>
                <td class="text-end"><span class="badge {{ $row->status === 'balanced' ? 'bg-success' : 'bg-warning' }}">{{ $row->status === 'balanced' ? '平' : '有差异' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无对账记录</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $records->links() }}</div>
@endsection
