@extends('layouts.admin')

@section('title', $isEdit ? '模板详情' : '创建新研发项目模板')

@section('content')
@php
    $deptNames = $departments->pluck('dept_name', 'id');
    $deptOrder = $departments->pluck('id')->values();
    $selectedDepts = $initialConfig['selected_depts'] ?? [];
    $initialDeptDays = (array) ($initialConfig['dept_days'] ?? []);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">{{ $isEdit ? '模板详情' : '创建新研发项目模板' }}</h1>
    <a href="{{ route('project.templates') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<form id="templateForm" action="{{ $isEdit ? route('project.templates.update', $template) : route('project.templates.store') }}" method="POST" onsubmit="return prepareSubmit(event)">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="card mb-3">
        <div class="card-header fw-semibold">模板信息</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">模板名称 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $template?->name) }}" required maxlength="128">
                </div>
                <div class="col-md-6">
                    <label class="form-label">模板描述</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $template?->description) }}" maxlength="2000">
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
                           data-dept-id="{{ $dept->id }}" @if($isEdit) @checked(in_array($dept->id, $selectedDepts)) @else checked @endif onchange="onDeptSelectionChange()">
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
                               data-dept-id="{{ $dept->id }}" value="{{ old('dept_days.'.$dept->id, $initialDeptDays[$dept->id] ?? 5) }}" min="1" max="365"
                               onchange="calculateStats()">
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">任务配置</span>
            <small class="text-muted">同一任务类型有多张图纸时，点该行“+”新增任务</small>
        </div>
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
                        <th style="width:96px;">操作</th>
                    </tr>
                    </thead>
                    <tbody id="taskBody">
                    @foreach($taskTypes as $index => $type)
                        <tr class="task-row task-row-base" data-dept-id="{{ $type->department_id }}" data-task-type-id="{{ $type->id }}" data-task-type-name="{{ $type->name }}">
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
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-add-task"
                                        onclick="addSameTypeTask(this)" title="新增同类型任务">
                                    <i class="fa fa-plus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-task d-none"
                                        onclick="removeTaskRow(this)" title="删除该任务">
                                    <i class="fa fa-trash"></i>
                                </button>
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
    const initialConfig = @json($initialConfig, JSON_UNESCAPED_UNICODE);
    let taskIndexSeed = {{ $taskTypes->count() }};

    function taskRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.task-row'));
    }

    /** 克隆一行任务，按 taskIndexSeed 重排 tasks[..] 名称，返回新行 */
    function cloneTaskRow(baseRow, clearName) {
        const clone = baseRow.cloneNode(true);
        clone.classList.add('task-row-extra');
        clone.classList.remove('task-row-base');

        const index = taskIndexSeed++;
        clone.querySelectorAll('[name]').forEach(function (element) {
            element.name = element.name.replace(/tasks\[\d+\]/g, 'tasks[' + index + ']');
        });

        const checked = clone.querySelector('.task-enabled');
        if (checked) checked.checked = true;
        clone.classList.remove('table-secondary');

        const name = clone.querySelector('.task-name');
        if (name) {
            if (clearName) {
                name.value = '';
                name.placeholder = (clone.dataset.taskTypeName || '') + '（请输入具体任务名称）';
            }
            name.maxLength = 128;
        }

        const removeButton = clone.querySelector('.btn-remove-task');
        if (removeButton) removeButton.classList.remove('d-none');

        // 同类型任务排在一起：插到该类型最后一行之后
        let anchor = baseRow;
        while (anchor.nextElementSibling && anchor.nextElementSibling.classList.contains('task-row')
            && anchor.nextElementSibling.dataset.taskTypeId === baseRow.dataset.taskTypeId) {
            anchor = anchor.nextElementSibling;
        }
        anchor.insertAdjacentElement('afterend', clone);

        const deptId = parseInt(clone.dataset.deptId || '0', 10);
        const executor = baseRow.querySelector('.task-executor');
        const auditor = baseRow.querySelector('.task-auditor');
        fillUserSelect(clone.querySelector('.task-executor'), deptId, executor ? executor.value : null, 'executor');
        fillUserSelect(clone.querySelector('.task-auditor'), deptId, auditor ? auditor.value : null, 'auditor');

        return clone;
    }

    function addSameTypeTask(button) {
        const row = button.closest('.task-row');
        const clone = cloneTaskRow(row, true);
        calculateStats();
        const name = clone.querySelector('.task-name');
        if (name) {
            name.focus();
            name.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    function removeTaskRow(button) {
        const row = button.closest('.task-row');
        if (!row || row.classList.contains('task-row-base')) return;
        row.remove();
        calculateStats();
    }

    /** 详情页回填模板配置，支持同一任务类型多行 */
    function hydrateConfig(data) {
        const selected = (data.selected_depts || []).map(Number);
        document.querySelectorAll('.dept-checkbox').forEach(function (checkbox) {
            checkbox.checked = selected.indexOf(parseInt(checkbox.value, 10)) !== -1;
        });
        Object.keys(data.dept_days || {}).forEach(function (deptId) {
            const input = document.querySelector('.dept-days[data-dept-id="' + deptId + '"]');
            if (input) input.value = data.dept_days[deptId];
        });

        const configs = data.tasks || [];
        const tbody = document.getElementById('taskBody');
        const baseRows = {};

        taskRows().forEach(function (row) {
            if (row.classList.contains('task-row-extra')) {
                row.remove();
                return;
            }
            baseRows[row.dataset.taskTypeId] = row;
            const enabled = row.querySelector('.task-enabled');
            if (enabled) enabled.checked = false;
            row.classList.add('table-secondary');
            const name = row.querySelector('.task-name');
            if (name) name.value = row.dataset.taskTypeName || name.value;
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            fillUserSelect(row.querySelector('.task-executor'), deptId, null, 'executor');
            fillUserSelect(row.querySelector('.task-auditor'), deptId, null, 'auditor');
        });

        const usedType = {};
        configs.slice().sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); })
            .forEach(function (config) {
                const typeKey = String(config.task_type_id);
                const base = baseRows[typeKey];
                if (!base) return;

                let target = base;
                if (usedType[typeKey]) {
                    target = cloneTaskRow(base, true);
                }
                usedType[typeKey] = true;

                target.dataset.deptId = String(config.department_id || base.dataset.deptId || '');
                const deptHidden = target.querySelector('input[name*="[department_id]"]');
                if (deptHidden) deptHidden.value = target.dataset.deptId;

                const enabled = target.querySelector('.task-enabled');
                if (enabled) enabled.checked = true;
                target.classList.remove('table-secondary');

                const name = target.querySelector('.task-name');
                if (name) name.value = config.name || base.dataset.taskTypeName || name.value;

                fillUserSelect(
                    target.querySelector('.task-executor'),
                    parseInt(target.dataset.deptId || '0', 10),
                    config.executor_id,
                    'executor'
                );
                fillUserSelect(
                    target.querySelector('.task-auditor'),
                    parseInt(target.dataset.deptId || '0', 10),
                    config.auditor_id,
                    'auditor'
                );

                tbody.appendChild(target);
            });

        Object.keys(baseRows).forEach(function (typeKey) {
            if (!usedType[typeKey]) {
                tbody.appendChild(baseRows[typeKey]);
            }
        });
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
            if (!taskName || !taskName.value.trim()) {
                errors.push('任务「' + (row.dataset.taskTypeName || '未命名') + '」需要填写具体任务名称');
            }
            const label = (taskName && taskName.value.trim()) || row.dataset.taskTypeName || '未命名任务';
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
        if (initialConfig) {
            hydrateConfig(initialConfig);
        }
        onDeptSelectionChange();
        calculateStats();
    });
</script>
@endsection
