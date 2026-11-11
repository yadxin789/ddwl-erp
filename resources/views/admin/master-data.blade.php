@extends('layouts.admin')

@section('title', '基础数据归口管理')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h1 class="h5 mb-2">基础数据归口管理</h1>
        <p class="text-muted small mb-2">
            总原则：每一类基础数据只对应一个归口部门。归口部门负责口径与命名标准，管理员负责系统维护。
            字典变更遵循“归口部门确认 → 管理员后台操作”，并保留停用能力，避免历史单据断链。
        </p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.master-data.task-types') }}" class="btn btn-sm btn-primary">
                <i class="fa fa-list-check me-1"></i>研发任务类型
            </a>
            <a href="{{ route('admin.master-data.units') }}" class="btn btn-sm btn-outline-primary">计量单位</a>
            <a href="{{ route('admin.master-data.tax-terms') }}" class="btn btn-sm btn-outline-primary">税率与结算方式</a>
            <a href="{{ route('admin.master-data.approval-flows') }}" class="btn btn-sm btn-outline-primary">审批流程</a>
            <a href="{{ route('admin.master-data.number-sequences') }}" class="btn btn-sm btn-outline-primary">单据编号规则</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>归口台账（{{ $domains->count() }} 类）</span>
        <span class="small text-muted">归口部门可调整，维护入口为空表示待建设</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:150px;">编码</th>
                <th style="min-width:160px;">基础数据</th>
                <th style="min-width:230px;">归口部门</th>
                <th style="width:110px;">维护入口</th>
                <th>归口职责</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($domains as $domain)
                <tr>
                    <td class="font-monospace small">{{ $domain->code }}</td>
                    <td>
                        <div>{{ $domain->name }}</div>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('admin.master-data.domains.update', $domain) }}" class="d-flex gap-1">
                            @csrf
                            @method('PUT')
                            <select name="owner_department_id" class="form-select form-select-sm">
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected($domain->owner_department_id === $department->id)>
                                        {{ $department->dept_name }}
                                    </option>
                                @endforeach
                            </select>
                            <select name="status" class="form-select form-select-sm" style="width:80px;">
                                <option value="1" @selected($domain->status)>启用</option>
                                <option value="0" @selected(! $domain->status)>停用</option>
                            </select>
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                    </td>
                    <td>
                        @if ($domain->route_name && \Illuminate\Support\Facades\Route::has($domain->route_name))
                            <a href="{{ route($domain->route_name) }}" class="btn btn-sm btn-outline-primary">进入</a>
                        @else
                            <span class="badge bg-light text-dark">待建设</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $domain->description }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
