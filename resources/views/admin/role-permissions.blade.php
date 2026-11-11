@extends('layouts.admin')

@section('title', '角色授权 · ' . \App\Support\DisplayText::role($role->name))

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ \App\Support\DisplayText::role($role->name) }}</span>
        <a href="{{ route('admin.permissions') }}" class="btn btn-sm btn-link">返回权限管理</a>
    </div>
    <form method="POST" action="{{ route('admin.permissions.role.update', $role) }}" class="card-body">
        @csrf
        @method('PUT')

        @if (in_array($role->name, ['role_sys_admin', 'system_admin'], true))
            <div class="alert alert-warning small">
                系统管理员只能持有运维与后台管理权限；提交业务权限会被拒绝（v1.3 §2.2 铁律）。
            </div>
        @endif

        <div class="row g-3">
            @foreach ($permissionGroups as $module => $items)
                <div class="col-md-4">
                    <div class="border rounded p-2 h-100">
                        <div class="fw-bold small mb-1">{{ \App\Support\DisplayText::module($module) }}</div>
                        @foreach ($items as $permission)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]"
                                       value="{{ $permission }}" id="p-{{ md5($permission) }}"
                                       @checked(in_array($permission, $rolePermissions))>
                                <label class="form-check-label small" for="p-{{ md5($permission) }}">{{ \App\Support\DisplayText::permission($permission) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            <button class="btn btn-primary">保存授权（覆盖式）</button>
            <span class="text-muted small ms-2">保存后立即生效，菜单与页面守卫同步变化。</span>
        </div>
    </form>
</div>
@endsection
