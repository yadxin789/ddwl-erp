@extends('layouts.admin')

@section('title', '请假管理')

@section('content')
<div class="card">
    <div class="card-header">员工请假</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hr.leaves.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <select name="employee_id" class="form-select form-select-sm" required>
                    @foreach ($emps as $e)<option value="{{ $e->id }}">{{ $e->emp_no }} {{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    @foreach(\App\Enums\HrLeaveType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from_date" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><input type="date" name="to_date" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">提交</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>单号</th><th>员工</th><th>类型</th><th>起止</th><th>天数</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($leaves as $l)
            <tr><td class="font-monospace">{{ $l->leave_no }}</td><td>{{ $l->employee->name ?? '-' }}</td><td>{{ \App\Enums\HrLeaveType::tryFrom($l->type)?->label() ?? $l->type }}</td><td>{{ $l->from_date }} ~ {{ $l->to_date }}</td><td>{{ $l->days }}天</td><td><span class="badge bg-secondary text-dark">{{ \App\Support\DisplayText::status($l->status) }}</span></td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无请假</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $leaves->links() }}</div>
</div>
@endsection
