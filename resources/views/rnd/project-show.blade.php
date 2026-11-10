@extends('layouts.admin')

@section('title', '研发项目详情')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>{{ $project->project_name }}（{{ $project->project_code }}） 进度 {{ $project->progress }}%</span>
        <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">返回</a>
    </div>
    <div class="card-body">
        @php
            $stageKeys = array_keys(\App\Models\Rnd\RndProject::STAGES);
            $cur = $project->stageIndex();
            $nextStage = $stageKeys[$cur + 1] ?? null;
        @endphp
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="small text-muted">研发阶段：</span>
            @foreach (\App\Models\Rnd\RndProject::STAGES as $key => $label)
                @php $i = array_search($key, $stageKeys, true); @endphp
                <span class="badge bg-{{ $i <= $cur ? 'primary' : 'light text-secondary border' }}">{{ $label }}</span>
                @if ($i < count($stageKeys) - 1)<span class="text-muted small">→</span>@endif
            @endforeach
            @if ($baseline)
                <span class="badge bg-success ms-2">基线：v{{ $baseline->version_no }}（{{ $baseline->file->file_name ?? '-' }}）</span>
            @else
                <span class="badge bg-warning text-dark ms-2">尚无基线图档（进试产会被拦截）</span>
            @endif
            @can('manage', $project)
                @if ($nextStage)
                <form method="POST" action="{{ route('rnd.projects.stage', $project) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="stage" value="{{ $nextStage }}">
                    <button class="btn btn-sm btn-outline-primary">推进到「{{ \App\Models\Rnd\RndProject::STAGES[$nextStage] }}」</button>
                </form>
                @endif
            @endcan
        </div>
        @if ($errors->has('stage'))
            <div class="alert alert-danger py-1 small">{{ $errors->first('stage') }}</div>
        @endif

        @can('createTask', $project)
        <h6 class="fw-semibold">任务分配（文档交付 + 期限）</h6>
        <form method="POST" action="{{ route('rnd.tasks.store', $project) }}" class="row g-2 mb-3 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label small">任务名称</label>
                <input type="text" name="name" class="form-control form-control-sm" maxlength="128"
                       placeholder="默认取交付物类型">
            </div>
            <div class="col-md-2">
                <label class="form-label small">交付物类型</label>
                <select name="task_type_id" id="newTaskType" class="form-select form-select-sm" required>
                    @foreach ($taskTypes as $t)
                        <option value="{{ $t->id }}" data-dept-id="{{ $t->department_id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="department_id" id="newTaskDept">
            </div>
            <div class="col-md-2">
                <label class="form-label small">执行人</label>
                <select name="executor_id" id="newTaskExecutor" class="form-select form-select-sm" required></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">审核人</label>
                <select name="auditor_id" id="newTaskAuditor" class="form-select form-select-sm"></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">截止日期</label>
                <input type="date" name="end_date" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">分配任务</button></div>
        </form>
        @else
        <div class="alert alert-light border small mb-3">任务分配由项目创建人（或研发经理）执行；你可以上传本人负责的交付物、申请延期。</div>
        @endcan

        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>任务</th><th>执行人</th><th>审核人</th><th>截止</th><th>状态</th><th>文档</th></tr></thead>
            <tbody>
                @forelse ($project->tasks as $t)
                <tr class="{{ $t->is_overdue ? 'table-danger' : '' }}">
                    <td>
                        {{ $t->name ?: ($t->taskType->name ?? '#' . $t->task_type_id) }}
                        @if($t->name && $t->taskType)
                            <div class="small text-muted">{{ $t->taskType->name }}</div>
                        @endif
                    </td>
                    <td>{{ $t->executor->username ?? '-' }}</td>
                    <td>{{ $t->auditor->username ?? '-' }}</td>
                    <td>{{ $t->end_date }}@if($t->is_overdue) <span class="badge bg-danger">逾期</span> @endif</td>
                    <td>{{ $t->status }}</td>
                    <td>
                        @can('upload', $t)
                        <form method="POST" action="{{ route('rnd.documents.upload', $t) }}" class="d-inline" enctype="multipart/form-data">
                            @csrf
                            <label class="btn btn-sm btn-outline-primary mb-0">
                                上传<input type="file" name="file" class="d-none" required onchange="this.form.submit()">
                            </label>
                        </form>
                        @endcan
                        <button class="btn btn-sm btn-outline-warning mb-0" data-bs-toggle="modal" data-bs-target="#extModal{{ $t->id }}">延期</button>
                        <div class="modal fade" id="extModal{{ $t->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <form method="POST" action="{{ route('rnd.extensions.store', $t) }}">
                                    @csrf
                                    <div class="modal-content">
                                        <div class="modal-header"><h6 class="modal-title">申请延期 - {{ $t->name ?: ($t->taskType->name ?? '#'.$t->id) }}</h6></div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label small">延期天数</label><input type="number" name="apply_days" class="form-control" min="1" max="90" value="7" required></div>
                                            <div class="mb-2"><label class="form-label small">延期原因</label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
                                        </div>
                                        <div class="modal-footer"><button class="btn btn-primary btn-sm">提交申请</button></div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        @foreach ($t->documents as $doc)
                            <div class="small">v{{ $doc->version_no }}:
                                @if ($doc->file)
                                    <a href="{{ route('files.download', $doc->file) }}">{{ $doc->file->file_name }}</a>
                                    @if ($doc->file->canPreview())
                                        <a href="{{ route('files.preview', $doc->file) }}" target="_blank" class="ms-1">预览</a>
                                    @endif
                                @else
                                    -
                                @endif
                                [{{ $doc->audit_status }}]
                                @if ($doc->is_baseline)
                                    <span class="badge bg-success">基线</span>
                                @elseif ($doc->superseded_by)
                                    <span class="badge bg-secondary">已被 v 替代</span>
                                @endif
                                @if ($doc->audit_status === 'approved' && ! $doc->is_baseline)
                                    @can('manage', $project)
                                    <form method="POST" action="{{ route('rnd.documents.baseline', $doc) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success py-0 px-1">设为基线</button>
                                    </form>
                                    @endcan
                                @endif
                                @if($doc->audit_status === 'pending')
                                @can('audit', $doc)
                                <form method="POST" action="{{ route('rnd.documents.audit', $doc) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="pass" value="1">
                                    <button class="btn btn-sm btn-success py-0 px-1">通过</button>
                                </form>
                                @else
                                <span class="text-muted">（待指定审核人判定）</span>
                                @endcan
                                @endif
                            </div>
                        @endforeach
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted">尚未分配任务</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- P1-05 简版 ECR/ECO --}}
<div class="card mt-3">
    <div class="card-header">工程变更单（ECR / ECO）</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
        @endif
        @can('manage', $project)
        <form method="POST" action="{{ route('rnd.ecr.store', $project) }}" class="row g-2 mb-2 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small">受影响 BOM</label>
                <select name="bom_id" id="ecr-bom" class="form-select form-select-sm" required>
                    @foreach ($boms as $b)
                        <option value="{{ $b->id }}" @selected(old('bom_id') == $b->id)>{{ $b->bom_code }} {{ $b->version }} @if($b->status !== 'active')（{{ $b->status }}）@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">变更类型</label>
                <select name="change_type" class="form-select form-select-sm">
                    <option value="bom">BOM 结构</option>
                    <option value="material">替代料</option>
                    <option value="process">工艺</option>
                    <option value="document">图档</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">优先级</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="normal">一般</option>
                    <option value="high">紧急</option>
                    <option value="low">低</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">计划生效日</label>
                <input type="date" name="effective_date" value="{{ old('effective_date') }}" min="{{ now()->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <button type="button" id="ecr-preview" class="btn btn-sm btn-outline-info w-100">预览影响</button>
            </div>
            <div class="col-md-6">
                <label class="form-label small">变更标题</label>
                <input name="title" value="{{ old('title') }}" class="form-control form-control-sm" required maxlength="128">
            </div>
            <div class="col-md-6">
                <label class="form-label small">变更范围（工序/图档/装配）</label>
                <input name="scope_description" value="{{ old('scope_description') }}" class="form-control form-control-sm" maxlength="500">
            </div>
            <div class="col-md-3">
                <label class="form-label small">受影响物料编码（留空=整个 BOM）</label>
                <input name="impacted_material_codes" value="{{ old('impacted_material_codes') }}" class="form-control form-control-sm" maxlength="1000" placeholder="EL000001,RM000002">
            </div>
            <div class="col-md-3">
                <label class="form-label small">替代物料编码（与上一栏一一对应）</label>
                <input name="replacement_material_codes" value="{{ old('replacement_material_codes') }}" class="form-control form-control-sm" maxlength="1000" placeholder="EL000002">
            </div>
            <div class="col-md-5">
                <label class="form-label small">变更原因</label>
                <input name="reason" value="{{ old('reason') }}" class="form-control form-control-sm" required maxlength="1000">
            </div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">提交</button></div>
        </form>
        <div id="ecr-impact-panel" class="alert alert-light border small d-none mb-3"></div>
        @endcan
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light"><tr><th>变更单号</th><th>标题</th><th>类型/优先级</th><th>受影响 BOM</th><th>影响评估</th><th>申请人</th><th>状态</th><th>ECO / 新版本</th></tr></thead>
            <tbody>
                @forelse ($ecrs as $e)
                <tr>
                    <td class="font-monospace">{{ $e->change_no }}@if($e->effective_date)<div class="small text-muted">生效 {{ $e->effective_date->format('Y-m-d') }}</div>@endif</td>
                    <td>{{ $e->title }}@if($e->scope_description)<div class="small text-muted">{{ $e->scope_description }}</div>@endif</td>
                    <td>{{ $e->change_type }}<div class="small text-muted">{{ $e->priority ?? 'normal' }}</div></td>
                    <td>{{ $e->bom->bom_code ?? '-' }} {{ $e->bom->version ?? '' }}</td>
                    <td class="small">
                        @php $ia = $e->impact_analysis ?? []; @endphp
                        @if (! empty($ia))
                            <span class="badge bg-{{ ($ia['risk_level'] ?? 'low') === 'high' ? 'danger' : (($ia['risk_level'] ?? 'low') === 'medium' ? 'warning text-dark' : 'secondary text-dark') }}">{{ $ia['risk_level'] ?? 'low' }}</span>
                            工单 {{ $ia['open_work_orders']['count'] ?? 0 }} / 在库物料 {{ count($ia['materials'] ?? []) }} / 波及单 {{ $ia['affected_sales_orders']['count'] ?? 0 }}
                            <div class="text-muted">{{ implode('；', $ia['risk_flags'] ?? []) ?: '无风险提示' }}</div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $e->applicant->name ?? '-' }}</td>
                    <td><span class="badge bg-{{ $e->status === 'approved' ? 'success' : ($e->status === 'rejected' ? 'danger' : 'warning text-dark') }}">{{ $e->status }}</span></td>
                    <td>
                        @if ($e->eco_no)
                            <div class="font-monospace small">{{ $e->eco_no }}</div>
                        @endif
                        @if ($e->newBom)
                            {{ $e->newBom->bom_code }} {{ $e->newBom->version }}
                        @else
                            <span class="text-muted">批准后生成</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-muted">暂无变更单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var usersByDept = @json($usersByDept, JSON_UNESCAPED_UNICODE);
    var defaultExecutors = @json($defaultExecutors);
    var defaultAuditors = @json($defaultAuditors);
    var typeSelect = document.getElementById('newTaskType');
    if (!typeSelect) return;

    function fill(select, deptId, selectedId, allowEmpty) {
        if (!select) return;
        select.innerHTML = '';
        if (allowEmpty) select.appendChild(new Option('不指定', ''));
        var list = usersByDept[deptId] || [];
        if (!list.length) {
            select.appendChild(new Option('该部门暂无在岗账号', ''));
            return;
        }
        list.forEach(function (user) {
            select.appendChild(new Option(user.username, user.id));
        });
        select.value = selectedId && list.some(function (u) { return String(u.id) === String(selectedId); })
            ? selectedId
            : list[0].id;
    }

    function refresh() {
        var option = typeSelect.options[typeSelect.selectedIndex];
        var deptId = option ? (option.dataset.deptId || '') : '';
        document.getElementById('newTaskDept').value = deptId;
        fill(document.getElementById('newTaskExecutor'), deptId, defaultExecutors[deptId], false);
        fill(document.getElementById('newTaskAuditor'), deptId, defaultAuditors[deptId], true);
    }

    typeSelect.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush

@push('scripts')
<script>
(function () {
    var btn = document.getElementById('ecr-preview');
    if (!btn) return;
    var panel = document.getElementById('ecr-impact-panel');
    var bom = document.getElementById('ecr-bom');
    btn.addEventListener('click', function () {
        if (!bom || !bom.value) return;
        panel.classList.remove('d-none');
        panel.textContent = '正在计算影响……';
        fetch(@json(route('rnd.ecr.impact', $project)) + '?bom_id=' + encodeURIComponent(bom.value), {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (d) {
            var flags = (d.risk_flags && d.risk_flags.length) ? d.risk_flags.join('；') : '无风险提示';
            panel.innerHTML =
                '<div><strong>影响等级：</strong>' + d.risk_level +
                '　<strong>BOM：</strong>' + d.bom.code + ' ' + d.bom.version +
                '　<strong>物料行：</strong>' + d.bom.line_count + '</div>' +
                '<div><strong>未完工工单：</strong>' + d.open_work_orders.count + ' 张（剩余 ' + d.open_work_orders.remaining_qty + '）；' +
                '<strong>波及销售单：</strong>' + d.affected_sales_orders.count + ' 张</div>' +
                '<div class="text-danger">' + flags + '</div>';
        }).catch(function (e) {
            panel.textContent = '影响分析失败：' + e.message;
        });
    });
})();
</script>
@endpush
@endsection
