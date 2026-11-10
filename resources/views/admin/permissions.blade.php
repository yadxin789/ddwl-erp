@extends('layouts.admin')

@section('title', '权限管理')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">角色（{{ $roles->count() }}）· 点击进入授权</div>
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>角色</th><th class="text-end">权限数</th><th class="text-end">操作</th></tr></thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td class="font-monospace small">{{ $role->name }}</td>
                            <td class="text-end">{{ $role->permissions_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.permissions.role', $role) }}" class="btn btn-sm btn-outline-primary">授权</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">岗位 → 角色绑定覆盖（{{ $positionCoverage->count() }} 岗）</div>
            <div class="card-body p-0" style="max-height:420px; overflow:auto;">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light"><tr><th>岗位</th><th>绑定角色</th></tr></thead>
                    <tbody>
                        @foreach ($positionCoverage as $row)
                            <tr>
                                <td class="small">{{ $row['pos_name'] }}<div class="text-muted font-monospace">{{ $row['pos_code'] }}</div></td>
                                <td class="small">
                                    @forelse ($row['bound'] as $role)
                                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ $role }}</span>
                                    @empty
                                        <span class="badge bg-danger">未绑定</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">权限库（按模块）</div>
            <div class="card-body small" style="max-height:280px; overflow:auto;">
                @foreach ($permissionGroups as $module => $items)
                    <div class="mb-1"><span class="badge bg-secondary">{{ $module }}</span>
                        <span class="text-muted">{{ $items->count() }} 项</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
