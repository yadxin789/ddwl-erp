@extends('layouts.admin')

@section('title', '采购计划')

@section('content')
<div class="card">
    <div class="card-header">电商采购计划</div>
    <div class="card-body">
        <form method="POST" action="{{ route('proc.plans.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4"><input name="title" class="form-control form-control-sm" placeholder="计划标题如 双11备货" required></div>
            <div class="col-md-2"><input type="date" name="plan_date" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><input name="total_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="预算金额"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建计划</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>计划号</th><th>标题</th><th>日期</th><th>预算</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($plans as $p)
            <tr><td class="font-monospace">{{ $p->plan_no }}</td><td>{{ $p->title }}</td><td>{{ $p->plan_date }}</td><td>¥{{ number_format($p->total_amount,2) }}</td><td>{{ \App\Enums\ProcurementPlanStatus::tryFrom($p->status)?->label() ?? $p->status }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无采购计划</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $plans->links() }}</div>
</div>
@endsection
