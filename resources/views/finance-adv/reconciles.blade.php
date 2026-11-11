@extends('layouts.admin')

@section('title', '对账管理')

@section('content')
<div class="card">
    <div class="card-header">对账管理</div>
    <div class="card-body">
        <form method="POST" action="{{ route('fin.reconciles.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input type="date" name="period" class="form-control form-control-sm" required></div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="bank">银行</option><option value="customer">客户</option><option value="supplier">供应商</option>
                </select>
            </div>
            <div class="col-md-2"><input name="book_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="账面金额" required></div>
            <div class="col-md-2"><input name="actual_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="实际金额" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">生成对账</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>对账号</th><th>期间</th><th>类型</th><th>账面</th><th>实际</th><th>差异</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr>
                <td class="font-monospace">{{ $r->reconcile_no }}</td>
                <td>{{ $r->period }}</td><td>{{ \App\Support\DisplayText::type($r->type) }}</td>
                <td>¥{{ number_format($r->book_amount,2) }}</td>
                <td>¥{{ number_format($r->actual_amount,2) }}</td>
                <td class="{{ $r->diff_amount != 0 ? 'text-danger' : '' }}">¥{{ number_format($r->diff_amount,2) }}</td>
                <td>{{ \App\Support\DisplayText::status($r->status) }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无对账单</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
