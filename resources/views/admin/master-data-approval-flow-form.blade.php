@extends('layouts.admin')

@section('title', $flow ? '编辑审批流程' : '新增审批流程')

@section('content')
@php
    $steps = old('steps', $flow?->steps_json ?? [['step' => 1, 'name' => '', 'permission' => '', 'approver_id' => null, 'delegate_ids' => []]]);
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0">{{ $flow ? '编辑审批流程' : '新增审批流程' }}</h1>
    <a href="{{ route('admin.master-data.approval-flows') }}" class="btn btn-sm btn-outline-secondary">返回列表</a>
</div>

<form method="POST" action="{{ $flow ? route('admin.master-data.approval-flows.update', $flow) : route('admin.master-data.approval-flows.store') }}">
    @csrf
    @if ($flow)
        @method('PUT')
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">流程编码 <span class="text-danger">*</span></label>
                    <input type="text" name="flow_code" class="form-control form-control-sm" required maxlength="64"
                           value="{{ old('flow_code', $flow->flow_code ?? '') }}" placeholder="如 flow_po_confirm">
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">流程名称 <span class="text-danger">*</span></label>
                    <input type="text" name="flow_name" class="form-control form-control-sm" required maxlength="128"
                           value="{{ old('flow_name', $flow->flow_name ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">业务类型 <span class="text-danger">*</span></label>
                    <input type="text" name="business_type" class="form-control form-control-sm" required maxlength="64"
                           value="{{ old('business_type', $flow->business_type ?? '') }}" placeholder="如 purchase_order.confirm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">状态</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="1" @selected(old('status', $flow->status ?? true))>启用</option>
                        <option value="0" @selected(! old('status', $flow->status ?? true))>停用</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>审批节点 <span class="small text-muted">按顺序审批；代批人可不选</span></span>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addStep()">添加节点</button>
        </div>
        <div class="card-body">
            <div id="stepList">
                @foreach ($steps as $index => $step)
                    <div class="border rounded p-2 mb-2 step-row">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">节点名称 <span class="text-danger">*</span></label>
                                <input type="text" name="steps[{{ $index }}][name]" class="form-control form-control-sm" required maxlength="128"
                                       value="{{ $step['name'] ?? '' }}" placeholder="如 采购主管主审">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">节点权限 <span class="text-danger">*</span></label>
                                <select name="steps[{{ $index }}][permission]" class="form-select form-select-sm" required>
                                    <option value="">请选择权限</option>
                                    @foreach ($permissions as $permission)
                                        <option value="{{ $permission }}" @selected(($step['permission'] ?? '') === $permission)>{{ $permission }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">审批人 <span class="text-danger">*</span></label>
                                <select name="steps[{{ $index }}][approver_id]" class="form-select form-select-sm" required>
                                    <option value="">请选择审批人</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected((int) ($step['approver_id'] ?? 0) === $user->id)>{{ $user->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">代批人</label>
                                <select name="steps[{{ $index }}][delegate_ids][]" class="form-select form-select-sm" multiple size="3">
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" @selected(in_array($user->id, array_map('intval', $step['delegate_ids'] ?? []), true))>{{ $user->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeStep(this)">删除</button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="small text-muted">规则：申请人不得审批自己提交的单据；代批必须填写理由；审批期间业务动作不执行。</div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-primary">保存</button>
        <a href="{{ route('admin.master-data.approval-flows') }}" class="btn btn-outline-secondary">取消</a>
    </div>
</form>

<template id="stepTemplate">
    <div class="border rounded p-2 mb-2 step-row">
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label small mb-1">节点名称 <span class="text-danger">*</span></label>
                <input type="text" data-name="name" class="form-control form-control-sm" required maxlength="128" placeholder="如 采购主管主审">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">节点权限 <span class="text-danger">*</span></label>
                <select data-name="permission" class="form-select form-select-sm" required>
                    <option value="">请选择权限</option>
                    @foreach ($permissions as $permission)
                        <option value="{{ $permission }}">{{ $permission }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">审批人 <span class="text-danger">*</span></label>
                <select data-name="approver_id" class="form-select form-select-sm" required>
                    <option value="">请选择审批人</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">代批人</label>
                <select data-name="delegate_ids" class="form-select form-select-sm" multiple size="3">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeStep(this)">删除</button>
            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
let stepIndex = {{ count($steps) }};

function addStep() {
    const template = document.getElementById('stepTemplate').content.cloneNode(true);
    const row = template.querySelector('.step-row');
    row.querySelector('[data-name="name"]').name = `steps[${stepIndex}][name]`;
    row.querySelector('[data-name="permission"]').name = `steps[${stepIndex}][permission]`;
    row.querySelector('[data-name="approver_id"]').name = `steps[${stepIndex}][approver_id]`;
    row.querySelector('[data-name="delegate_ids"]').name = `steps[${stepIndex}][delegate_ids][]`;
    stepIndex++;
    document.getElementById('stepList').appendChild(row);
}

function removeStep(button) {
    const rows = document.querySelectorAll('#stepList .step-row');
    if (rows.length <= 1) {
        alert('至少保留一个审批节点');
        return;
    }
    button.closest('.step-row').remove();
}
</script>
@endpush
