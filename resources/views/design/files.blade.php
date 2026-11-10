@extends('layouts.admin')

@section('title', $title)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="fw-bold">{{ $title }}</span>
        <span class="text-muted small ms-2">仅显示任务类型名称含「{{ $keyword }}」的图档</span>
    </div>
    <form class="d-flex gap-1" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="项目名称">
        <button class="btn btn-sm btn-outline-secondary">筛选</button>
    </form>
</div>

<div class="card">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>项目</th><th>任务</th><th>文件</th><th>版本</th><th>审核状态</th><th>上传人</th></tr>
        </thead>
        <tbody>
        @forelse ($documents as $doc)
            <tr>
                <td class="small">{{ $doc->project->project_name ?? '-' }}</td>
                <td class="small">{{ $doc->task->name ?: ($doc->task->taskType->name ?? '-') }}</td>
                <td class="small">
                    @if ($doc->file)
                        <a href="{{ route('files.download', $doc->file) }}">{{ $doc->file->file_name }}</a>
                        @if ($doc->file->canPreview())
                            <a href="{{ route('files.preview', $doc->file) }}" target="_blank" class="ms-1">预览</a>
                        @endif
                    @else
                        -
                    @endif
                </td>
                <td class="small">{{ $doc->version_no }}</td>
                <td class="small">{{ $doc->audit_status }}</td>
                <td class="small">{{ $doc->uploader->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无{{ $keyword }}图档</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">{{ $documents->links() }}</div>
@endsection
