@extends('layouts.admin')

@section('title', '创建研发项目')

@section('content')
@php
    $deptNames = $departments->pluck('dept_name', 'id');
    $deptOrder = $departments->pluck('id')->values();
    $defaultStart = date('Y-m-d');
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">创建研发项目</h1>
    <a href="{{ route('rnd.projects') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>返回列表
    </a>
</div>

<form id="projectForm" action="{{ route('rnd.projects.store') }}" method="POST" onsubmit="return prepareSubmit(event)">
    @csrf

    <div class="card mb-3">
        <div class="card-header fw-semibold">基础信息</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">项目名称 <span class="text-danger">*</span></label>
                    <input type="text" name="project_name" class="form-control" value="{{ old('project_name') }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">项目编码</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code') }}" placeholder="留空自动生成">
                </div>
                <div class="col-md-2">
                    <label class="form-label">版本号</label>
                    <input type="text" name="version" class="form-control" value="{{ old('version', 'V1.0') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">产品名称</label>
                    <input type="text" name="product_name" class="form-control" value="{{ old('product_name') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">产品型号</label>
                    <input type="text" name="product_model" class="form-control" value="{{ old('product_model') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">开始日期 <span class="text-danger">*</span></label>
                    <input type="date" id="start_date" name="start_date" class="form-control"
                           value="{{ old('start_date', $defaultStart) }}" required onchange="calculateTaskDates()">
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label">导入模板</label>
                    <div class="input-group">
                        <select id="template_id" class="form-select">
                            <option value="">请选择项目模板</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-primary" onclick="loadTemplate()">
                            <i class="fa fa-download me-1"></i>导入模板配置
                        </button>
                    </div>
                    <div class="form-text">导入后会回填参与部门、部门天数、任务、执行人和审核人。</div>
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
                           name="selected_depts[]" value="{{ $dept->id }}" id="dept_{{ $dept->id }}"
                           data-dept-id="{{ $dept->id }}" checked onchange="onDeptSelectionChange()">
                    <label class="form-check-label" for="dept_{{ $dept->id }}">{{ $dept->dept_name }}</label>
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
                               onchange="calculateTaskDates()">
                    </div>
                @endforeach
            </div>
            <div class="form-text mt-2">部门之间串行排期，部门内任务共用同一组起止日期。</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">任务配置</span>
            <small class="text-muted">已列出该部门全部启用任务类型；“必选”默认勾选，“可选”按项目需要勾选；同一类型多张图纸点“+”</small>
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
                        <th style="min-width:125px;">起始日期</th>
                        <th style="min-width:125px;">截止日期</th>
                        <th style="min-width:130px;">执行人</th>
                        <th style="min-width:130px;">审核人</th>
                        <th style="width:96px;">操作</th>
                    </tr>
                    </thead>
                    <tbody id="taskBody">
                    @foreach($taskTypes as $index => $type)
                        <tr class="task-row task-row-base {{ $type->is_required ? '' : 'table-secondary' }}" data-dept-id="{{ $type->department_id }}" data-task-type-id="{{ $type->id }}" data-task-type-name="{{ $type->name }}" data-required="{{ $type->is_required ? 1 : 0 }}">
                            <td>
                                {{ $type->name }}
                                @if ($type->is_required)
                                    <span class="badge bg-danger-subtle text-danger ms-1">必选</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary ms-1">可选</span>
                                @endif
                                <input type="hidden" name="tasks[{{ $index }}][task_type_id]" value="{{ $type->id }}">
                            </td>
                            <td>
                                {{ $deptNames[$type->department_id] ?? '未分配' }}
                                <input type="hidden" name="tasks[{{ $index }}][department_id]" value="{{ $type->department_id }}">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input task-enabled"
                                       name="tasks[{{ $index }}][enabled]" value="1" @checked($type->is_required)
                                       onchange="onTaskEnabledChange(this)">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm task-name"
                                       name="tasks[{{ $index }}][name]" value="{{ $type->name }}" maxlength="128">
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm task-start"
                                       name="tasks[{{ $index }}][start_date]" readonly>
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm task-end"
                                       name="tasks[{{ $index }}][end_date]" readonly>
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
        <div class="card-header fw-semibold">项目统计</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-2"><small class="text-muted d-block">起始日期</small><strong id="statStart">-</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">任务总数量</small><strong id="statTasks">0</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">部门数量</small><strong id="statDepts">0</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">项目总天数</small><strong id="statDays">0</strong></div>
                <div class="col-md-2"><small class="text-muted d-block">项目截止日期</small><strong id="statEnd">-</strong></div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="fa fa-check me-1"></i>创建项目
        </button>
        <a href="{{ route('rnd.projects') }}" class="btn btn-outline-secondary">返回</a>
    </div>
</form>

<div class="modal fade" id="globalErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">项目创建失败</h5>
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
    const usersByDept = @json($usersByDept, JSON_UNESCAPED_UNICODE);
    const auditorsByDept = @json($auditorsByDept, JSON_UNESCAPED_UNICODE);
    const defaultExecutors = @json($defaultExecutors);
    const defaultAuditors = @json($defaultAuditors);
    const deptOrder = @json($deptOrder);
    let taskIndexSeed = {{ $taskTypes->count() }};

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
        calculateTaskDates();
    }

    function onDeptSelectionChange() {
        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            const visible = getSelectedDepts().indexOf(deptId) !== -1;
            row.dataset.visible = visible ? '1' : '0';
            row.style.display = visible ? '' : 'none';
        });
        calculateTaskDates();
    }

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
        calculateTaskDates();
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
        calculateTaskDates();
    }

    function fillUserSelect(select, deptId, selectedId, type) {
        if (!select) return;
        select.innerHTML = '';
        const list = type === 'auditor' ? (auditorsByDept[deptId] || []) : (usersByDept[deptId] || []);
        if (!list.length) {
            select.appendChild(new Option(type === 'auditor' ? '该部门未配置部门经理' : '无部门人员', ''));
            return;
        }
        list.forEach(function (user) {
            select.appendChild(new Option(user.label, user.id));
        });
        const defaults = type === 'executor' ? defaultExecutors : defaultAuditors;
        const allowedSelected = selectedId && list.some(function (user) { return String(user.id) === String(selectedId); });
        select.value = (allowedSelected ? selectedId : (defaults[deptId] || list[0].id));
    }

    function addDays(date, days) {
        const result = new Date(date.getTime());
        result.setDate(result.getDate() + days);
        return result;
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }

    function calculateTaskDates() {
        const startInput = document.getElementById('start_date');
        const startValue = startInput ? startInput.value : '';
        const statStart = document.getElementById('statStart');
        if (statStart) statStart.textContent = startValue || '-';
        if (!startValue) return;

        let cursor = new Date(startValue + 'T00:00:00');
        let totalTasks = 0;
        let totalDays = 0;
        let projectEnd = new Date(cursor.getTime());
        const selected = getSelectedDepts();

        selected.forEach(function (deptId) {
            const daysInput = document.querySelector('.dept-days[data-dept-id="' + deptId + '"]');
            const days = Math.max(1, parseInt(daysInput ? daysInput.value : '1', 10) || 1);
            const deptStart = new Date(cursor.getTime());
            const deptEnd = addDays(deptStart, days - 1);

            taskRows().forEach(function (row) {
                if (parseInt(row.dataset.deptId || '0', 10) !== deptId) return;
                if (!isTaskEnabled(row)) return;
                const startField = row.querySelector('.task-start');
                const endField = row.querySelector('.task-end');
                if (startField) startField.value = formatDate(deptStart);
                if (endField) endField.value = formatDate(deptEnd);
                totalTasks++;
            });

            projectEnd = new Date(deptEnd.getTime());
            cursor = addDays(deptEnd, 1);
            totalDays += days;
        });

        document.getElementById('statTasks').textContent = totalTasks;
        document.getElementById('statDepts').textContent = selected.length;
        document.getElementById('statDays').textContent = totalDays;
        document.getElementById('statEnd').textContent = selected.length ? formatDate(projectEnd) : '-';
    }

    function selectAllDepts() {
        document.querySelectorAll('.dept-checkbox').forEach(function (checkbox) { checkbox.checked = true; });
        onDeptSelectionChange();
    }

    function deselectAllDepts() {
        document.querySelectorAll('.dept-checkbox').forEach(function (checkbox) { checkbox.checked = false; });
        onDeptSelectionChange();
    }

    function loadTemplate() {
        const templateId = document.getElementById('template_id').value;
        if (!templateId) return;

        fetch('{{ url('/project-templates') }}/' + templateId + '/config', { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
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

                // 先清掉手工新增的同类任务，恢复每个任务类型一行的基线
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

                // 模板里同一任务类型可以有多条（如“原理图”下的电源/主控/驱动），按顺序逐条生成行
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

                // 未出现在模板中的任务类型保留为未选中状态，统一排到已用任务之后
                Object.keys(baseRows).forEach(function (typeKey) {
                    if (!usedType[typeKey]) {
                        tbody.appendChild(baseRows[typeKey]);
                    }
                });

                onDeptSelectionChange();
                calculateTaskDates();
            })
            .catch(function () {
                showGlobalModal('模板配置加载失败，请稍后重试');
            });
    }

    function showGlobalModal(message) {
        document.getElementById('globalErrorBody').innerHTML = message;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('globalErrorModal'));
        modal.show();
    }

    function prepareSubmit(event) {
        const errors = [];
        const startDate = document.getElementById('start_date').value;
        const selected = getSelectedDepts();

        if (!startDate) errors.push('请选择开始日期');
        if (!selected.length) errors.push('请至少选择一个参与部门');

        let activeCount = 0;
        taskRows().forEach(function (row) {
            const deptId = parseInt(row.dataset.deptId || '0', 10);
            const visible = selected.indexOf(deptId) !== -1;
            const enabled = isTaskEnabled(row);
            const active = visible && enabled;

            if (!active) {
                row.querySelectorAll('input, select').forEach(function (element) {
                    element.removeAttribute('name');
                });
                return;
            }

            activeCount++;
            const name = row.querySelector('.task-name');
            const executor = row.querySelector('.task-executor');
            const auditor = row.querySelector('.task-auditor');
            if (!name || !name.value.trim()) {
                errors.push('任务「' + (row.dataset.taskTypeName || '未命名') + '」需要填写具体任务名称');
            }
            const label = (name && name.value.trim()) || row.dataset.taskTypeName || '未命名任务';
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

        const params = new URLSearchParams(window.location.search);
        const templateId = params.get('template');
        if (templateId) {
            document.getElementById('template_id').value = templateId;
            loadTemplate();
        }

        onDeptSelectionChange();
        calculateTaskDates();
    });
</script>
@endsection
