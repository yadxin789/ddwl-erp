@extends('layouts.admin')

@section('title', '岗位管理')

@section('content')
<div class="card mb-3">
    <div class="card-header">岗位（{{ $positions->count() }}）· 每个岗位必须至少绑定 1 个角色</div>
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>岗位编码</th><th>岗位名称</th><th>部门</th><th>绑定角色</th><th>数据范围</th><th class="text-end">状态</th></tr>
        </thead>
        <tbody>
            @foreach ($positions as $position)
                <tr>
                    <td class="font-monospace">{{ $position->pos_code }}</td>
                    <td>{{ $position->pos_name }}</td>
                    <td class="small">{{ $position->department->dept_name ?? '-' }}</td>
                    <td class="small">
                        @foreach (($position->role_names ?? []) as $role)
                            <span class="badge bg-primary-subtle text-primary-emphasis">{{ \App\Support\DisplayText::role($role) }}</span>
                        @endforeach
                        @if (empty($position->role_names))
                            <span class="badge bg-danger">未绑定</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $position->data_scope ?: '-' }}</td>
                    <td class="text-end">
                        <span class="badge {{ $position->status ? 'bg-success' : 'bg-secondary' }}">{{ $position->status ? '启用' : '停用' }}</span>
                        <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#pos-{{ $position->id }}">编辑</button>
                    </td>
                </tr>
                <tr class="collapse" id="pos-{{ $position->id }}">
                    <td colspan="6">
                        <form method="POST" action="{{ route('admin.positions.update', $position) }}" class="row g-2 align-items-end">
                            @csrf
                            @method('PUT')
                            <div class="col-md-2">
                                <label class="form-label small mb-1">岗位编码</label>
                                <input type="text" name="pos_code" value="{{ $position->pos_code }}" class="form-control form-control-sm" required maxlength="16">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">岗位名称</label>
                                <input type="text" name="pos_name" value="{{ $position->pos_name }}" class="form-control form-control-sm" required maxlength="64">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">所属部门</label>
                                <select name="department_id" class="form-select form-select-sm" required>
                                    @foreach ($departments as $dept)
                                        <option value="{{ $dept->id }}" @selected($position->department_id === $dept->id)>{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">绑定角色（可多选）</label>
                                <select name="role_names[]" class="form-select form-select-sm" multiple size="4" required>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role }}" @selected(in_array($role, $position->role_names ?? []))>{{ \App\Support\DisplayText::role($role) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">数据范围</label>
                                <select name="data_scope_type" class="form-select form-select-sm">
                                    <option value="all" @selected(($position->data_scope_type ?? 'self') === 'all')>全部数据</option>
                                    <option value="department" @selected(($position->data_scope_type ?? 'self') === 'department')>本部门</option>
                                    <option value="self" @selected(($position->data_scope_type ?? 'self') === 'self')>仅本人</option>
                                    <option value="none" @selected(($position->data_scope_type ?? 'self') === 'none')>不含业务数据</option>
                                </select>
                                <input type="text" name="data_scope" value="{{ $position->data_scope }}" class="form-control form-control-sm mt-1" maxlength="120" placeholder="文字说明">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small mb-1">状态</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="1" @selected($position->status === 1)>启用</option>
                                    <option value="0" @selected($position->status === 0)>停用</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <button class="btn btn-sm btn-primary">保存岗位</button>
                            </div>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-header">新增岗位</div>
    <form method="POST" action="{{ route('admin.positions.store') }}" class="card-body row g-2 align-items-end">
        @csrf
        <div class="col-md-2">
            <label class="form-label small mb-1">岗位编码</label>
            <input type="text" name="pos_code" class="form-control form-control-sm" required maxlength="16" placeholder="D-XXX-P01">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">岗位名称</label>
            <input type="text" name="pos_name" class="form-control form-control-sm" required maxlength="64">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">所属部门</label>
            <select name="department_id" class="form-select form-select-sm" required>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}">{{ $dept->dept_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">绑定角色（至少 1 个）</label>
            <select name="role_names[]" class="form-select form-select-sm" multiple size="4" required>
                @foreach ($roles as $role)
                    <option value="{{ $role }}">{{ \App\Support\DisplayText::role($role) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">数据范围</label>
            <select name="data_scope_type" class="form-select form-select-sm">
                <option value="all">全部数据</option>
                <option value="department">本部门</option>
                <option value="self" selected>仅本人</option>
                <option value="none">不含业务数据</option>
            </select>
            <input type="text" name="data_scope" class="form-control form-control-sm mt-1" maxlength="120" placeholder="文字说明">
        </div>
        <div class="col-md-1">
            <label class="form-label small mb-1">状态</label>
            <select name="status" class="form-select form-select-sm">
                <option value="1">启用</option>
                <option value="0">停用</option>
            </select>
        </div>
        <div class="col-md-12">
            <button class="btn btn-sm btn-primary">创建岗位</button>
        </div>
    </form>
</div>
@endsection
