@extends('layouts.admin')

@section('title', '研发任务类型维护')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-1">研发任务类型维护</h1>
        <div class="small text-muted">
            归口部门：研发部。研发部确定类型清单与命名口径，管理员在此统一维护。
            已被项目任务或项目模板引用的类型只能停用，不能删除。
        </div>
    </div>
    <a href="{{ route('admin.master-data') }}" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-arrow-left me-1"></i>返回归口台账
    </a>
</div>

<div class="card mb-3">
    <div class="card-header">新增任务类型</div>
    <form method="POST" action="{{ route('admin.master-data.task-types.store') }}" class="card-body">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">类型名称 <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="64" placeholder="如：可靠性测试报告">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">执行部门 <span class="text-danger">*</span></label>
                <select name="department_id" class="form-select form-select-sm" required>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->dept_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">说明</label>
                <input type="text" name="description" class="form-control form-control-sm" maxlength="255">
            </div>
            <div class="col-md-1 form-check ms-2 pt-3">
                <input type="checkbox" name="is_required" value="1" class="form-check-input" id="newRequired" checked>
                <label class="form-check-label small" for="newRequired">必选</label>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">排序</label>
                <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="{{ $nextSortOrder }}">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">状态</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-sm btn-primary w-100">添加</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>任务类型（{{ $types->total() }}）</span>
        <span class="small text-muted">任务 / 模板引用数用于判断能否删除</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:110px;">编码</th>
                <th style="min-width:180px;">类型名称</th>
                <th style="width:120px;">执行部门</th>
                <th style="min-width:160px;">说明</th>
                <th style="width:64px;">必选</th>
                <th style="width:72px;">排序</th>
                <th style="width:80px;">状态</th>
                <th style="width:100px;">引用</th>
                <th style="width:120px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($types as $type)
                @php
                    $usedByTasks = (int) ($taskUsage[$type->id] ?? 0);
                    $usedByTemplates = (int) ($templateUsage[$type->id] ?? 0);
                @endphp
                <tr class="{{ $type->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $type->code }}</td>
                    <td>
                        <input type="text" name="name" form="typeForm{{ $type->id }}" value="{{ $type->name }}"
                               class="form-control form-control-sm" required maxlength="64">
                    </td>
                    <td>
                        <select name="department_id" form="typeForm{{ $type->id }}" class="form-select form-select-sm" required>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($type->department_id === $department->id)>
                                    {{ $department->dept_name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" name="description" form="typeForm{{ $type->id }}" value="{{ $type->description }}"
                               class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td class="text-center">
                        <input type="checkbox" name="is_required" value="1" form="typeForm{{ $type->id }}"
                               class="form-check-input" @checked($type->is_required)>
                    </td>
                    <td>
                        <input type="number" name="sort_order" form="typeForm{{ $type->id }}" value="{{ $type->sort_order }}"
                               class="form-control form-control-sm" min="0">
                    </td>
                    <td>
                        <select name="status" form="typeForm{{ $type->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($type->status)>启用</option>
                            <option value="0" @selected(! $type->status)>停用</option>
                        </select>
                    </td>
                    <td class="small">
                        <span class="badge bg-info">任务 {{ $usedByTasks }}</span>
                        <span class="badge bg-secondary">模板 {{ $usedByTemplates }}</span>
                    </td>
                    <td class="text-nowrap">
                        <form id="typeForm{{ $type->id }}" method="POST"
                              action="{{ route('admin.master-data.task-types.update', $type) }}" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary" title="保存">保存</button>
                        </form>
                        @if ($usedByTasks === 0 && $usedByTemplates === 0)
                            <form method="POST" action="{{ route('admin.master-data.task-types.destroy', $type) }}"
                                  class="d-inline" onsubmit="return confirm('确定删除任务类型「{{ $type->name }}」吗？');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="删除">删除</button>
                            </form>
                        @else
                            <span class="badge bg-warning text-dark" title="已被引用，只能停用">已引用</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $types->links() }}</div>
</div>
@endsection
