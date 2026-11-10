@extends('layouts.admin')

@section('title', '绩效考核')

@section('content')
<div class="card">
    <div class="card-header">绩效考核</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hra.performances.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <select name="employee_id" class="form-select form-select-sm" required>
                    @foreach ($emps as $e)<option value="{{ $e->id }}">{{ $e->emp_no }} {{ $e->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="period" class="form-control form-control-sm" placeholder="2026Q4" required></div>
            <div class="col-md-1"><input name="score" type="number" min="0" max="100" class="form-control form-control-sm" placeholder="分数" required></div>
            <div class="col-md-2">
                <select name="grade" class="form-select form-select-sm">
                    <option value="">等级</option><option>S</option><option>A</option><option>B</option><option>C</option><option>D</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">录入</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>员工</th><th>期间</th><th>分数</th><th>等级</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr><td>{{ $r->employee->name ?? '-' }}</td><td>{{ $r->period }}</td><td>{{ $r->score }}</td><td><span class="badge bg-info">{{ $r->grade }}</span></td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">暂无绩效记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
