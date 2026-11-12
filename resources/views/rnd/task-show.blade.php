@extends('layouts.admin')

@section('title', '任务详情')

@section('content')
@php
    $statusMap = [
        'pending' => ['待开始', 'secondary'],
        'in_progress' => ['进行中', 'primary'],
        'submitted' => ['已提交待审', 'info'],
        'approved' => ['已通过', 'success'],
        'rejected' => ['已驳回', 'danger'],
        'overdue' => ['已逾期', 'warning'],
        'completed' => ['已完成', 'success'],
        'cancelled' => ['已取消', 'dark'],
    ];
    [$label, $badge] = $statusMap[$task->status] ?? [$task->status, 'secondary'];
    $auditMap = ['pending' => ['待审核', 'warning'], 'approved' => ['已通过', 'success'], 'rejected' => ['已驳回', 'danger']];
    $paused = $project && $project->status === 'paused';
@endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h5 class="mb-1">{{ $task->name }} <span class="badge bg-{{ $badge }}">{{ $label }}</span></h5>
        <div class="text-muted small">
            项目：<a href="{{ route('rnd.projects.show', $project) }}">{{ $project->project_name ?? '—' }}</a>
            · {{ $project->project_code ?? '—' }}
            · 任务类型 {{ $task->taskType->name ?? '—' }}
        </div>
    </div>
    <div class="d-flex gap-2">
        @if ($project)<a href="{{ route('rnd.projects.show', $project) }}" class="btn btn-sm btn-outline-secondary">返回项目</a>@endif
        <a href="{{ route('rnd.my-tasks') }}" class="btn btn-sm btn-outline-secondary">我的任务</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">任务信息</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">所属项目</dt>
                    <dd class="col-sm-8">{{ $project->project_name ?? '—' }}（{{ $project->project_code ?? '—' }}）</dd>
                    <dt class="col-sm-4">部门</dt>
                    <dd class="col-sm-8">{{ $task->department_id ? (\App\Models\Organization\Department::find($task->department_id)?->dept_name ?? '—') : '—' }}</dd>
                    <dt class="col-sm-4">执行人</dt>
                    <dd class="col-sm-8">{{ $task->executor->identity_label ?? '—' }}</dd>
                    <dt class="col-sm-4">审核人</dt>
                    <dd class="col-sm-8">{{ $task->auditor->identity_label ?? '—' }}</dd>
                    <dt class="col-sm-4">任务状态</dt>
                    <dd class="col-sm-8"><span class="badge bg-{{ $badge }}">{{ $label }}</span>
                        @if ($task->is_overdue)<span class="badge bg-danger ms-1">逾期</span>@endif
                    </dd>
                    <dt class="col-sm-4">开始日期</dt>
                    <dd class="col-sm-8">{{ $task->start_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-sm-4">截止日期</dt>
                    <dd class="col-sm-8">
                        {{ $task->end_date?->format('Y-m-d') ?? '—' }}
                        @if ($task->extension_days)<span class="text-muted">（已延期累计 {{ $task->extension_days }} 天）</span>@endif
                    </dd>
                    <dt class="col-sm-4">项目状态</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-{{ $project->statusBadge() ?? 'secondary' }}">{{ $project->statusLabel() ?? '—' }}</span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">任务操作</div>
            <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                @if ($paused)
                <span class="text-muted small">项目已暂停：任务的上传 / 提交 / 审核已冻结，恢复项目后自动可用。</span>
                @else
                    @if ($canExecute && in_array($task->status, ['pending', 'overdue', 'rejected'], true))
                    <form method="POST" action="{{ route('rnd.tasks.start', $task) }}">@csrf<button class="btn btn-sm btn-primary">开始任务</button></form>
                    @endif

                    @if ($canExecute && in_array($task->status, ['pending', 'in_progress', 'overdue', 'rejected'], true))
                    <form method="POST" action="{{ route('rnd.tasks.submit', $task) }}">@csrf<button class="btn btn-sm btn-success">提交审核</button></form>
                    @endif

                    @if ($canUpload)
                    <form method="POST" action="{{ route('rnd.documents.upload', $task) }}" enctype="multipart/form-data" class="d-flex gap-1">
                        @csrf
                        <input type="file" name="file" class="form-control form-control-sm" style="max-width:220px" required>
                        <button class="btn btn-sm btn-outline-primary">上传交付物</button>
                    </form>
                    @endif

                    @if ($canDecide && $task->status === 'submitted')
                    <form method="POST" action="{{ route('rnd.tasks.decide', $task) }}" class="d-flex gap-1 align-items-center">
                        @csrf
                        <input type="text" name="remark" class="form-control form-control-sm" placeholder="审核意见" style="max-width:180px">
                        <button class="btn btn-sm btn-success" name="pass" value="1">通过</button>
                        <button class="btn btn-sm btn-danger" name="pass" value="0">驳回</button>
                    </form>
                    @endif
                @endif

                @if ($canExecute)
                <button class="btn btn-sm btn-warning" data-bs-toggle="collapse" data-bs-target="#extForm">申请延期</button>
                @endif
            </div>
            @if ($canExecute)
            <div class="collapse" id="extForm">
                <div class="card-body border-top">
                    <form method="POST" action="{{ route('rnd.extensions.store', $task) }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-auto">
                            <label class="form-label small mb-0">延期天数</label>
                            <input type="number" name="apply_days" min="1" max="90" value="1" class="form-control form-control-sm" style="width:90px" required>
                        </div>
                        <div class="col">
                            <label class="form-label small mb-0">延期理由</label>
                            <input type="text" name="reason" maxlength="500" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-auto"><button class="btn btn-sm btn-warning">提交延期申请</button></div>
                    </form>
                    <div class="text-muted small mt-1">延期申请提交后进入审批，通过后自动顺延截止日期并解除逾期标记。</div>
                </div>
            </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>交付文件（{{ $documents->count() }} 个版本）</span>
                <span class="text-muted small">按版本倒序</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>版本</th><th>文件名</th><th>上传人 / 时间</th><th>审核</th><th>操作</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($documents as $doc)
                        @php [$al, $ab] = $auditMap[$doc->audit_status] ?? [$doc->audit_status, 'secondary']; @endphp
                        <tr>
                            <td>v{{ $doc->version_no }}@if ($doc->is_baseline)<span class="badge bg-dark ms-1">基线</span>@endif</td>
                            <td class="text-truncate" style="max-width:220px">{{ $doc->file->original_name ?? ('#' . $doc->file_id) }}</td>
                            <td class="small">{{ $doc->uploader->identity_label ?? '—' }}<br>{{ $doc->created_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <span class="badge bg-{{ $ab }}">{{ $al }}</span>
                                @if ($doc->audit_remark)<div class="text-muted small">{{ $doc->audit_remark }}</div>@endif
                            </td>
                            <td class="text-nowrap">
                                @if ($doc->file_id)
                                <a href="{{ route('files.preview', $doc->file_id) }}" target="_blank" class="btn btn-sm btn-outline-info">预览</a>
                                <a href="{{ route('files.download', $doc->file_id) }}" class="btn btn-sm btn-outline-success">下载</a>
                                @endif
                                @if ($doc->audit_status === 'approved' && ! $doc->is_baseline)
                                <form method="POST" action="{{ route('rnd.documents.baseline', $doc->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-dark">设基线</button></form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">暂无交付文件</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">延期申请记录（{{ $extensions->count() }} 条）</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>申请人</th><th>审批人</th><th>天数</th><th>理由</th><th>状态</th><th>时间</th></tr>
            </thead>
            <tbody>
                @forelse ($extensions as $e)
                @php $emap = ['pending' => ['待审批', 'warning'], 'approved' => ['已通过', 'success'], 'rejected' => ['已驳回', 'danger']]; [$el, $eb] = $emap[$e->status] ?? [$e->status, 'secondary']; @endphp
                <tr>
                    <td>{{ $e->applicant->identity_label ?? '—' }}</td>
                    <td>{{ $e->approver->identity_label ?? '—' }}</td>
                    <td>{{ $e->apply_days }} 天</td>
                    <td class="text-truncate" style="max-width:280px">{{ $e->reason }}</td>
                    <td><span class="badge bg-{{ $eb }}">{{ $el }}</span></td>
                    <td class="small">{{ $e->approved_at?->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-3">暂无延期申请</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
