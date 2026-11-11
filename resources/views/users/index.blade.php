@extends('layouts.admin')

@section('title', '用户管理')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>用户列表（共 {{ $users->total() }} 条）</span>
        @can('system.user.create')
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>新建用户</a>
        @endcan
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="登录账号/用户名/邮箱" value="{{ request('keyword') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100">查询</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>登录账号</th><th>用户名（姓名）</th><th>邮箱</th><th>部门/岗位</th><th>角色</th><th>状态</th><th>最后登录</th><th>操作</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                    <tr>
                        <td><span class="font-monospace">{{ $user->username }}</span></td>
                        <td>{{ $user->name }}</td>
                        <td class="small">{{ $user->email ?? '-' }}</td>
                        <td class="small">{{ $user->department->dept_name ?? '-' }} / {{ $user->position->pos_name ?? '-' }}</td>
                        <td>
                            @foreach ($user->roles as $role)
                                <span class="badge bg-primary">{{ $role->name }}</span>
                            @endforeach
                        </td>
                        <td>@if ($user->status === 1) <span class="badge bg-success">启用</span> @else <span class="badge bg-secondary">禁用</span> @endif</td>
                        <td class="small">{{ $user->last_login_at ?? '-' }}</td>
                        <td>
                            @can('system.user.edit')
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">暂无用户数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</div>
@endsection
