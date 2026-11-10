@extends('layouts.admin')

@section('title', '部门管理')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">部门（{{ $departments->count() }}）</div>
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>编码</th><th>名称</th><th class="text-end">岗位数</th><th style="width:260px;">排序 / 状态</th></tr>
                </thead>
                <tbody>
                    @foreach ($departments as $dept)
                        <tr>
                            <td class="font-monospace">{{ $dept->dept_code }}</td>
                            <td>{{ $dept->dept_name }}</td>
                            <td class="text-end">{{ $dept->positions_count }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.departments.update', $dept) }}" class="d-flex gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input type="number" name="sort_order" value="{{ $dept->sort_order }}" class="form-control form-control-sm" style="width:70px;" min="0">
                                    <select name="status" class="form-select form-select-sm" style="width:80px;">
                                        <option value="1" @selected($dept->status === 1)>启用</option>
                                        <option value="0" @selected($dept->status === 0)>停用</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary">保存</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">新增部门</div>
            <form method="POST" action="{{ route('admin.departments.store') }}" class="card-body">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-1">部门编码</label>
                    <input type="text" name="dept_code" class="form-control form-control-sm" required maxlength="16" placeholder="D-XXX">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">部门名称</label>
                    <input type="text" name="dept_name" class="form-control form-control-sm" required maxlength="64">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">排序号</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" min="0" value="0">
                </div>
                <button class="btn btn-primary btn-sm w-100">创建部门</button>
            </form>
        </div>
    </div>
</div>
@endsection
