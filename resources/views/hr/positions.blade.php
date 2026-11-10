@extends('layouts.admin')

@section('title', '岗位编制')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">在岗（已绑岗位的账号）</div><div class="fs-4 fw-bold">{{ $totalUsers }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">未绑岗位账号</div><div class="fs-4 fw-bold text-danger">{{ $unboundUsers }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">岗位数（含停用）</div><div class="fs-4 fw-bold">{{ $positions->count() }}</div>
    </div></div></div>
</div>

<div class="card">
    <div class="card-header">岗位编制表</div>
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>岗位编码</th><th>岗位</th><th>部门</th><th class="text-end">在岗</th><th>绑定角色</th><th class="text-end">状态</th></tr>
        </thead>
        <tbody>
        @foreach ($positions as $position)
            <tr>
                <td class="font-monospace small">{{ $position->pos_code }}</td>
                <td>{{ $position->pos_name }}</td>
                <td class="small">{{ $position->department->dept_name ?? '-' }}</td>
                <td class="text-end">{{ $position->users_count }}</td>
                <td class="small">
                    @forelse (($position->role_names ?? []) as $role)
                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ $role }}</span>
                    @empty
                        <span class="badge bg-danger">未绑定</span>
                    @endforelse
                </td>
                <td class="text-end"><span class="badge {{ $position->status ? 'bg-success' : 'bg-secondary' }}">{{ $position->status ? '启用' : '停用' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
