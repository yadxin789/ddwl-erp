@extends('layouts.admin')

@section('title', '我的任务')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>我的任务（执行中 + 待我审核，共 {{ $tasks->total() }} 条）</span>
        <div class="d-flex gap-2">
            <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">研发项目</a>
            @can('rnd.create')
            <a href="{{ route('rnd.documents') }}" class="btn btn-sm btn-primary">交付物上传台</a>
            @endcan
            @can('rnd.approve')
            <a href="{{ route('rnd.documents.review') }}" class="btn btn-sm btn-outline-primary">待我审核</a>
            @endcan
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>项目</th><th>交付物</th><th>截止日期</th><th>状态</th><th>最新文档</th><th>操作</th></tr>
            </thead>
            <tbody>
                @php
                    $smap = ['pending'=>'待开始','submitted'=>'已提交待审','approved'=>'已通过','rejected'=>'已驳回','overdue'=>'已逾期','completed'=>'已完成','cancelled'=>'已取消'];
                    $bmap = ['pending'=>'secondary','submitted'=>'info','approved'=>'success','rejected'=>'danger','overdue'=>'warning','completed'=>'success','cancelled'=>'secondary'];
                @endphp
                @forelse ($tasks as $task)
                <tr>
                    <td>
                        <a href="{{ route('rnd.projects.show', $task->project_id) }}">{{ $task->project->project_name ?? '-' }}</a>
                        <div class="small text-muted font-monospace">{{ $task->project->project_code ?? '' }}</div>
                    </td>
                    <td>
                        {{ $task->name ?: ($task->taskType->name ?? '-') }}
                        @if($task->name && $task->taskType)
                            <div class="small text-muted">{{ $task->taskType->name }}</div>
                        @endif
                    </td>
                    <td>{{ optional($task->end_date)->format('Y-m-d') }}</td>
                    <td>
                        <span class="badge bg-{{ $bmap[$task->status] ?? 'secondary' }}">{{ $smap[$task->status] ?? $task->status }}</span>
                        @if ($task->is_overdue)<span class="badge bg-danger">逾期</span>@endif
                    </td>
                    <td class="small">
                        @php $last = $task->documents->sortByDesc('version_no')->first(); @endphp
                        @if ($last)
                            v{{ $last->version_no }} ·
                            @if ($last->file)
                                <a href="{{ route('files.download', $last->file) }}">{{ $last->file->file_name }}</a>
                                @if ($last->file->canPreview())
                                    <a href="{{ route('files.preview', $last->file) }}" target="_blank" class="ms-1">预览</a>
                                @endif
                            @else
                                -
                            @endif
                            ·
                            <span class="text-{{ $last->audit_status === 'approved' ? 'success' : ($last->audit_status === 'rejected' ? 'danger' : 'warning') }}">{{ $last->audit_status }}</span>
                        @else
                            <span class="text-muted">未提交</span>
                        @endif
                    </td>
                    <td class="d-flex gap-1">
                        <a href="{{ route('rnd.tasks.show', $task) }}" class="btn btn-sm btn-outline-dark">详情</a>
                        <a href="{{ route('rnd.projects.show', $task->project_id) }}" class="btn btn-sm btn-outline-primary">项目</a>
                        @can('execute', $task)
                            @if (in_array($task->status, ['pending', 'overdue', 'rejected'], true))
                            <form method="POST" action="{{ route('rnd.tasks.start', $task) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary">开始</button>
                            </form>
                            @elseif (! in_array($task->status, ['submitted', 'approved'], true))
                            <form method="POST" action="{{ route('rnd.tasks.submit', $task) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-info">提交</button>
                            </form>
                            @endif
                        @endcan
                        @can('decide', $task)
                            @if ($task->status === 'submitted')
                            <form method="POST" action="{{ route('rnd.tasks.decide', $task) }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="pass" value="1">
                                <button class="btn btn-sm btn-success">通过</button>
                            </form>
                            <form method="POST" action="{{ route('rnd.tasks.decide', $task) }}">
                                @csrf
                                <input type="hidden" name="pass" value="0">
                                <button class="btn btn-sm btn-outline-danger">驳回</button>
                            </form>
                            @endif
                        @endcan
                        @can('rnd.create')
                        @if ($task->executor_id === auth()->id())
                        <form method="POST" action="{{ route('rnd.documents.upload', $task) }}" enctype="multipart/form-data" class="d-flex gap-1">
                            @csrf
                            <input type="file" name="file" class="form-control form-control-sm" style="max-width:170px" required>
                            <button class="btn btn-sm btn-outline-success">上传</button>
                        </form>
                        @endif
                        @endcan
                        @can('rnd.approve')
                        @if ($task->auditor_id === auth()->id() && $task->status === 'submitted')
                        <a href="{{ route('rnd.documents.review') }}" class="btn btn-sm btn-info">去审核</a>
                        @endif
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">暂无任务</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $tasks->links() }}</div>
</div>
@endsection
