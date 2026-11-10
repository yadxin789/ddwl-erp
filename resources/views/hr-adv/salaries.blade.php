@extends('layouts.admin')

@section('title', '工资管理')

@section('content')
<div class="card">
    <div class="card-header">工资条</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hra.salaries.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <select name="employee_id" class="form-select form-select-sm" required>
                    @foreach ($emps as $e)<option value="{{ $e->id }}">{{ $e->emp_no }} {{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-1"><input name="period" class="form-control form-control-sm" placeholder="202611" required></div>
            <div class="col-md-2"><input name="base" type="number" step="0.01" class="form-control form-control-sm" placeholder="基本工资" required></div>
            <div class="col-md-2"><input name="bonus" type="number" step="0.01" class="form-control form-control-sm" placeholder="奖金"></div>
            <div class="col-md-2"><input name="deduction" type="number" step="0.01" class="form-control form-control-sm" placeholder="扣款"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">生成</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>员工</th><th>期间</th><th>基本</th><th>奖金</th><th>扣款</th><th>实发</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr><td>{{ $r->employee->name ?? '-' }}</td><td>{{ $r->period }}</td><td>{{ \App\Support\MaskingService::salary($r->base) }}</td><td>{{ \App\Support\MaskingService::salary($r->bonus) }}</td><td>{{ \App\Support\MaskingService::salary($r->deduction) }}</td><td><strong>{{ \App\Support\MaskingService::salary($r->net) }}</strong></td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无工资记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
