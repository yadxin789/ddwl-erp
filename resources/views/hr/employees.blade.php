@extends('layouts.admin')

@section('title', '员工管理')

@section('content')
<div class="card">
    <div class="card-header">员工花名册</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hr.employees.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="emp_no" class="form-control form-control-sm" placeholder="工号 E001" required></div>
            <div class="col-md-2"><input name="name" class="form-control form-control-sm" placeholder="姓名" required></div>
            <div class="col-md-2"><input name="phone" class="form-control form-control-sm" placeholder="电话"></div>
            <div class="col-md-2">
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">部门</option>
                    @foreach ($deps as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="position" class="form-control form-control-sm" placeholder="岗位"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">入职</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>工号</th><th>姓名</th><th>部门</th><th>岗位</th><th>电话</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($emps as $e)
            <tr><td class="font-monospace">{{ $e->emp_no }}</td><td>{{ $e->name }}</td><td>{{ $e->department->name ?? '-' }}</td><td>{{ $e->position ?? '-' }}</td><td>{{ $e->phone ?? '-' }}</td><td><span class="badge bg-secondary text-dark">{{ $e->status }}</span></td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无员工</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $emps->links() }}</div>
</div>
@endsection
