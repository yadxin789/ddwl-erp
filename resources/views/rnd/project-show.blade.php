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
            <div class="col-md-3">
                <label class="form-label small">交付物类型</label>
                <select name="task_type_id" class="form-select form-select-sm" required>
                    @foreach ($taskTypes as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">执行人</label>
                <select name="executor_id" class="form-select form-select-sm" required>
                    @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">审核人</label>
                <select name="auditor_id" class="form-select form-select-sm">
                    <option value="">不指定</option>
                    @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                </select>
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
            <thead class="table-light"><tr><th>交付物</th><th>执行人</th><th>审核人</th><th>截止</th><th>状态</th><th>文档</th></tr></thead>
            <tbody>
                @forelse ($project->tasks as $t)
                <tr class="{{ $t->is_overdue ? 'table-danger' : '' }}">
                    <td>{{ $t->taskType->name ?? '#' . $t->task_type_id }}</td>
                    <td>{{ $t->executor->name ?? '-' }}</td>
                    <td>{{ $t->auditor->name ?? '-' }}</td>
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
                                        <div class="modal-header"><h6 class="modal-title">申请延期 - {{ $t->taskType->name ?? '#'.$t->id }}</h6></div>
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
        @if ($errors->has('bom_id') || $errors->has('title') || $errors->has('reason'))
            <div class="alert alert-danger py-1 small">{{ $errors->first('bom_id') ?: $errors->first('title') ?: $errors->first('reason') }}</div>
        @endif
        @can('manage', $project)
        <form method="POST" action="{{ route('rnd.ecr.store', $project) }}" class="row g-2 mb-3 align-items-end">
            @csrf
            <div class="col-md-3">
                <label class="form-label small">受影响 BOM</label>
                <select name="bom_id" class="form-select form-select-sm" required>
                    @foreach ($boms as $b)
                        <option value="{{ $b->id }}">{{ $b->bom_code }} {{ $b->version }} @if($b->status !== 'active')（{{ $b->status }}）@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">变更类型</label>
                <select name="change_type" class="form-select form-select-sm">
                    <option value="bom">BOM 结构</option>
                    <option value="material">替代料</option>
                    <option value="process">工艺</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small">变更标题</label>
                <input name="title" class="form-control form-control-sm" required maxlength="128">
            </div>
            <div class="col-md-3">
                <label class="form-label small">变更原因</label>
                <input name="reason" class="form-control form-control-sm" required maxlength="1000">
            </div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">提交</button></div>
        </form>
        @endcan
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light"><tr><th>变更单号</th><th>标题</th><th>类型</th><th>受影响 BOM</th><th>申请人</th><th>状态</th><th>新版本 BOM</th></tr></thead>
            <tbody>
                @forelse ($ecrs as $e)
                <tr>
                    <td class="font-monospace">{{ $e->change_no }}</td>
                    <td>{{ $e->title }}</td>
                    <td>{{ $e->change_type }}</td>
                    <td>{{ $e->bom->bom_code ?? '-' }} {{ $e->bom->version ?? '' }}</td>
                    <td>{{ $e->applicant->name ?? '-' }}</td>
                    <td>
                        <span class="badge bg-{{ $e->status === 'approved' ? 'success' : ($e->status === 'rejected' ? 'danger' : 'warning text-dark') }}">{{ $e->status }}</span>
                    </td>
                    <td>
                        @if ($e->newBom)
                            {{ $e->newBom->bom_code }} {{ $e->newBom->version }}
                        @else
                            <span class="text-muted">批准后生成</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-muted">暂无变更单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
