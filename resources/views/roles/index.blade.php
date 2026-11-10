@extends('layouts.admin')

@section('title', '角色权限')

@section('content')
<div class="card">
    <div class="card-header">角色列表（共 {{ $roles->count() }} 个）</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>角色</th><th class="text-center">权限数</th><th class="text-center">用户数</th><th>权限明细</th></tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                    <tr>
                        <td><span class="badge bg-primary">{{ $role->name }}</span></td>
                        <td class="text-center">{{ $role->permissions_count }}</td>
                        <td class="text-center">{{ $role->users_count }}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#perm-{{ $role->id }}">
                                <i class="fa-solid fa-eye me-1"></i>查看
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse" id="perm-{{ $role->id }}">
                        <td colspan="4" class="bg-light">
                            @forelse ($role->permissions as $perm)
                                <span class="badge bg-light text-dark border me-1 mb-1">{{ $perm->name }}</span>
                            @empty
                                <span class="text-muted">无权限</span>
                            @endforelse
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">暂无角色</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
