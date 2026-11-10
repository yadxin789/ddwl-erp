@extends('layouts.admin')

@section('title', '交付物上传')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>待我交付（未提交 / 已驳回 / 逾期，共 {{ $tasks->total() }} 条）</span>
        <div class="d-flex gap-2">
            <a href="{{ route('rnd.my-tasks') }}" class="btn btn-sm btn-outline-secondary">我的任务</a>
        </div>
    </div>
    <div class="card-body pb-0">
        <p class="text-muted small mb-3">上传后任务转为「已提交待审」，审核通过后计入项目进度。同一任务可上传多个版本，版本号自动递增。</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>项目</th><th>交付物</th><th>截止日期</th><th>状态</th><th>已提交版本</th><th>上传</th></tr>
            </thead>
            <tbody>
                @php
                    $smap = ['pending'=>'待开始','submitted'=>'已提交待审','approved'=>'已通过','rejected'=>'已驳回','overdue'=>'已逾期'];
                    $bmap = ['pending'=>'secondary','submitted'=>'info','approved'=>'success','rejected'=>'danger','overdue'=>'warning'];
                @endphp
                @forelse ($tasks as $task)
                <tr>
                    <td>
                        <a href="{{ route('rnd.projects.show', $task->project_id) }}">{{ $task->project->project_name ?? '-' }}</a>
                        <div class="small text-muted font-monospace">{{ $task->project->project_code ?? '' }}</div>
                    </td>
                    <td>{{ $task->taskType->name ?? '-' }}</td>
                    <td>{{ optional($task->end_date)->format('Y-m-d') }}</td>
                    <td><span class="badge bg-{{ $bmap[$task->status] ?? 'secondary' }}">{{ $smap[$task->status] ?? $task->status }}</span></td>
                    <td>
                        @forelse ($task->documents->sortByDesc('version_no') as $doc)
                            <div class="small">
                                v{{ $doc->version_no }} ·
                                @if ($doc->file)
                                    <a href="{{ route('files.download', $doc->file) }}">{{ $doc->file->file_name }}</a>
                                @else
                                    -
                                @endif
                                ·
                                <span class="text-{{ $doc->audit_status === 'approved' ? 'success' : ($doc->audit_status === 'rejected' ? 'danger' : 'warning') }}">{{ $doc->audit_status }}</span>
                                @if ($doc->audit_remark)<span class="text-muted">（{{ $doc->audit_remark }}）</span>@endif
                            </div>
                        @empty
                            <span class="text-muted small">无</span>
                        @endforelse
                    </td>
                    <td>
                        <form method="POST" action="{{ route('rnd.documents.upload', $task) }}" enctype="multipart/form-data" class="d-flex gap-1">
                            @csrf
                            <input type="file" name="file" class="form-control form-control-sm" style="max-width:220px" required>
                            <button class="btn btn-sm btn-primary">提交</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">没有待交付的任务</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $tasks->links() }}</div>
</div>
@endsection
