@extends('layouts.admin')

@section('title', '创建新研发项目模板')

@section('content')
@php
    $deptNames = $departments->pluck('dept_name', 'id');
    $deptOrder = $departments->pluck('id')->values();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">创建新研发项目模板</h1>
    <a href="{{ route('project.templates') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<form id="templateForm" action="{{ route('project.templates.store') }}" method="POST" onsubmit="return prepareSubmit(event)">
    @csrf

    <div class="card mb-3">
        <div class="card-header fw-semibold">模板信息</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">模板名称 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="128">
                </div>
                <div class="col-md-6">
                    <label class="form-label">模板描述</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}" maxlength="2000">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">选择参与部门</span>
            <span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllDepts()">全选</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllDepts()">取消全选</button>
            </span>
        </div>
        <div class="card-body">
            @forelse($departments as $dept)
                <div class="form-check form-check-inline me-4 mb-2">
                    <input class="form-check-input dept-checkbox" type="checkbox"
                           name="selected_depts[]" value="{{ $dept->id }}" id="tpl_dept_{{ $dept->id }}"
                           data-dept-id="{{ $dept->id }}" checked onchange="onDeptSelectionChange()">
                    <label class="form-check-label" for="tpl_dept_{{ $dept->id }}">{{ $dept->dept_name }}</label>
                </div>
            @empty
                <div class="text-muted">暂无可用部门，请先在任务类型中维护部门归属。</div>
            @endforelse
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">部门天数配置</div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($departments as $dept)
                    <div class="col-md-3">
                        <label class="form-label">{{ $dept->dept_name }}（天）</label>
                        <input type="number" name="dept_days[{{ $dept->id }}]" class="form-control dept-days"
                               data-dept-id="{{ $dept->id }}" value="5" min="1" max="365"
                               onchange="calculateStats()">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">任务配置</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="min-width:170px;">任务类型</th>
                        <th style="min-width:90px;">部门</th>
                        <th style="width:56px;">选中</th>
                        <th style="min-width:180px;">任务名称</th>
                        <th style="min-width:130px;">执行人</th>
                        <th style="min-width:130px;">审核人</th>
                    </tr>
                    </thead>
                    <tbody id="taskBody">
                    @foreach($taskTypes as $index => $type)
                        <tr class="task-row" data-dept-id="{{ $type->department_id }}" data-task-type-id="{{ $type->id }}">
                            <td>
                                {{ $type->name }}
                                <input type="hidden" name="tasks[{{ $index }}][task_type_id]" value="{{ $type->id }}">
                            </td>
                            <td>
                                {{ $deptNames[$type->department_id] ?? '未分配' }}
                                <input type="hidden" name="tasks[{{ $index }}][department_id]" value="{{ $type->department_id }}">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input task-enabled"
                                       name="tasks[{{ $index }}][enabled]" value="1" checked
                                       onchange="onTaskEnabledChange(this)">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm task-name"
                                       name="tasks[{{ $index }}][name]" value="{{ $type->name }}" maxlength="128">
                            </td>
                            <td>
                                <select class="form-select form-select-sm task-executor" name="tasks[{{ $index }}][executor_id]"></select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm task-auditor" name="tasks[{{ $index }}][auditor_id]"></select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold">模板统计</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted d-block">任务总数量</small><strong id="statTasks">0</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">部门数量</small><strong id="statDepts">0</strong></div>
                <div class="col-md-3"><small class="text-muted d-block">项目总天数</small><strong id="statDays">0</strong></div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <a href="{{ route('project.templates') }}" class="btn btn-outline-secondary">返回列表</a>
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-check me-1"></i>保存模板
        </button>
    </div>
</form>

<div class="modal fade" id="globalErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">模板保存失败</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="globalErrorBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const usersByDept = @json($usersByDept);
    const defaultExecutors = @json($defaultExecutors);
    const defaultAuditors = @json($defaultAuditors);
    const deptOrder = @json($deptOrder);

    function taskRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.task-row'));
    }

    function getSelectedDepts() {
        return deptOrder.filter(function (deptId) {
            const checkbox = document.querySelector('.dept-checkbox[data-dept-id="' + deptId + '"]');
            return checkbox && checkbox.checked;
        });
    }

    function isTaskEnabled(row) {
        const checkbox = row.querySelector('.task-enabled');
        return checkbox && checkbox.checked;
    }

    function onTaskEnabledChange(checkbox) {
        const row = checkbox.closest('.task-row');
        row.classList.toggle('table-secondary', !checkbox.checked);
        calculateStats();
    }

    function onDeptSelectionChange() {
        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            const visible = getSelectedDepts().indexOf(deptId) !== -1;
            row.dataset.visible = visible ? '1' : '0';
            row.style.display = visible ? '' : 'none';
        });
        calculateStats();
    }

    function fillUserSelect(select, deptId, selectedId, type) {
        if (!select) return;
        select.innerHTML = '';
        const list = usersByDept[deptId] || [];
        if (!list.length) {
            select.appendChild(new Option('无部门人员', ''));
            return;
        }
        list.forEach(function (user) {
            select.appendChild(new Option(user.name, user.id));
        });
        const defaults = type === 'executor' ? defaultExecutors : defaultAuditors;
        select.value = selectedId || defaults[deptId] || list[0].id;
    }

    function calculateStats() {
        let totalTasks = 0;
        let totalDays = 0;
        const selected = getSelectedDepts();

        selected.forEach(function (deptId) {
            const daysInput = document.querySelector('.dept-days[data-dept-id="' + deptId + '"]');
            totalDays += Math.max(1, parseInt(daysInput ? daysInput.value : '1', 10) || 1);
        });

        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            if (selected.indexOf(deptId) !== -1 && isTaskEnabled(row)) totalTasks++;
        });

        document.getElementById('statTasks').textContent = totalTasks;
        document.getElementById('statDepts').textContent = selected.length;
        document.getElementById('statDays').textContent = totalDays;
    }

    function selectAllDepts() {
        document.querySelectorAll('.dept-checkbox').forEach(function (checkbox) { checkbox.checked = true; });
        onDeptSelectionChange();
    }

    function deselectAllDepts() {
        document.querySelectorAll('.dept-checkbox').forEach(function (checkbox) { checkbox.checked = false; });
        onDeptSelectionChange();
    }

    function showGlobalModal(message) {
        document.getElementById('globalErrorBody').innerHTML = message;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('globalErrorModal'));
        modal.show();
    }

    function prepareSubmit(event) {
        const errors = [];
        const selected = getSelectedDepts();
        const name = document.querySelector('input[name="name"]').value.trim();

        if (!name) errors.push('请输入模板名称');
        if (!selected.length) errors.push('请至少选择一个参与部门');

        let activeCount = 0;
        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            const visible = selected.indexOf(deptId) !== -1;
            const active = visible && isTaskEnabled(row);

            if (!active) {
                row.querySelectorAll('input, select').forEach(function (element) {
                    element.removeAttribute('name');
                });
                return;
            }

            activeCount++;
            const taskName = row.querySelector('.task-name');
            const executor = row.querySelector('.task-executor');
            const auditor = row.querySelector('.task-auditor');
            const label = (taskName && taskName.value.trim()) || '未命名任务';
            if (!executor || !executor.value) errors.push('任务「' + label + '」需要选择执行人');
            if (!auditor || !auditor.value) errors.push('任务「' + label + '」需要选择审核人');
        });

        if (!activeCount) errors.push('请至少启用一个属于已选部门的任务');

        if (errors.length) {
            event.preventDefault();
            showGlobalModal(errors.map(function (item) { return '<div>• ' + item + '</div>'; }).join(''));
            return false;
        }

        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            fillUserSelect(row.querySelector('.task-executor'), deptId, null, 'executor');
            fillUserSelect(row.querySelector('.task-auditor'), deptId, null, 'auditor');
        });
        onDeptSelectionChange();
        calculateStats();
    });
</script>
@endsection
