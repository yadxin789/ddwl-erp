@extends('layouts.admin')

@section('title', '审批流程维护')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-1">审批流程维护</h1>
        <div class="small text-muted">归口部门：总经理室。已有待审批实例的流程不能改节点；已有审批实例的流程不能删除，只能停用。</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.master-data.approval-flows.create') }}" class="btn btn-sm btn-primary">新增审批流程</a>
        <a href="{{ route('admin.master-data') }}" class="btn btn-sm btn-outline-secondary">返回归口台账</a>
    </div>
</div>

<div class="card">
    <div class="card-header">审批流程（{{ $flows->count() }}）</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:170px;">流程编码</th>
                <th style="min-width:200px;">流程名称</th>
                <th style="min-width:180px;">业务类型</th>
                <th>审批节点</th>
                <th style="width:80px;">状态</th>
                <th style="width:80px;">实例</th>
                <th style="width:150px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($flows as $flow)
                @php $instances = (int) ($instanceCounts[$flow->id] ?? 0); @endphp
                <tr class="{{ $flow->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $flow->flow_code }}</td>
                    <td>{{ $flow->flow_name }}</td>
                    <td class="font-monospace small">{{ $flow->business_type }}</td>
                    <td class="small">
                        @foreach (($flow->steps_json ?? []) as $step)
                            <div>
                        <span class="badge bg-secondary">{{ $step['step'] ?? '?' }}</span>
                                {{ $step['name'] ?? '-' }}：
                                {{ $userNames[$step['approver_id'] ?? 0] ?? ('用户#' . ($step['approver_id'] ?? '-')) }}
                                <span class="text-muted">/ {{ $step['permission'] ?? '-' }}</span>
                                @if (! empty($step['delegate_ids']))
                                    <span class="text-muted">（代批：{{ collect($step['delegate_ids'])->map(fn($id) => $userNames[$id] ?? ('#' . $id))->implode('、') }}）</span>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td>@if($flow->status)<span class="badge bg-success">启用</span>@else<span class="badge bg-secondary">停用</span>@endif</td>
                    <td><span class="badge bg-info">{{ $instances }}</span></td>
                    <td class="text-nowrap">
                        <a href="{{ route('admin.master-data.approval-flows.edit', $flow) }}" class="btn btn-sm btn-outline-secondary">编辑</a>
                        @if ($instances === 0)
                            <form method="POST" action="{{ route('admin.master-data.approval-flows.destroy', $flow) }}" class="d-inline"
                                  onsubmit="return confirm('确定删除审批流程「{{ $flow->flow_name }}」吗？');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        @else
                            <span class="badge bg-warning text-dark">有实例</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
