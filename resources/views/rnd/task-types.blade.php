@extends('layouts.admin')

@section('title', '研发任务类型字典')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>研发交付物类型字典（共 {{ $types->total() }} 类）</span>
        <div class="d-flex gap-2">
            @if (auth()->user()?->hasPermissionTo('admin.masterdata.manage'))
                <a href="{{ route('admin.master-data.task-types') }}" class="btn btn-sm btn-primary">
                    <i class="fa fa-pen-to-square me-1"></i>去后台维护
                </a>
            @endif
            <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">返回项目</a>
        </div>
    </div>
    <div class="card-body pb-0">
        <div class="alert alert-light border small mb-3">
            本页只读查看。任务类型归口部门为<strong>研发部</strong>，新增、改名、停用、删除由管理员在
            「后台管理 → 基础数据管理 → 研发任务类型维护」统一操作；已被项目或模板引用的类型只能停用。
        </div>
    </div>
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
        <tr>
            <th style="width:110px;">编码</th>
            <th style="width:80px;">排序</th>
            <th>类型名称</th>
            <th>说明</th>
            <th style="width:110px;">执行部门</th>
            <th style="width:80px;">必选</th>
            <th style="width:80px;">状态</th>
        </tr>
        </thead>
        <tbody>
            @foreach ($types as $t)
            <tr class="{{ $t->status ? '' : 'table-secondary' }}">
                <td class="font-monospace small">{{ $t->code }}</td>
                <td>{{ $t->sort_order }}</td>
                <td>{{ $t->name }}</td>
                <td class="small text-muted">{{ $t->description }}</td>
                <td>{{ $t->department->dept_name ?? '-' }}</td>
                <td>@if($t->is_required)<span class="badge bg-danger">必选</span>@else<span class="badge bg-light text-dark">可选</span>@endif</td>
                <td>@if($t->status)<span class="badge bg-success">启用</span>@else<span class="badge bg-secondary">停用</span>@endif</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="card-footer">{{ $types->links() }}</div>
</div>
@endsection
