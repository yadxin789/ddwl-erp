@extends('layouts.admin')

@section('title', '交付物审核')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>待审核交付物（共 {{ $documents->total() }} 份）</span>
        <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">研发项目</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>项目</th><th>交付物</th><th>执行人</th><th>上传人</th><th>版本 / 文件</th><th>提交时间</th><th>审核</th></tr>
            </thead>
            <tbody>
                @forelse ($documents as $doc)
                <tr>
                    <td>
                        <a href="{{ route('rnd.projects.show', $doc->project_id) }}">{{ $doc->project->project_name ?? '-' }}</a>
                        <div class="small text-muted font-monospace">{{ $doc->project->project_code ?? '' }}</div>
                    </td>
                    <td>
                        {{ $doc->task->name ?: ($doc->task->taskType->name ?? '-') }}
                        @if($doc->task->name && $doc->task->taskType)
                            <div class="small text-muted">{{ $doc->task->taskType->name }}</div>
                        @endif
                    </td>
                    <td>{{ $doc->task->executor->username ?? '-' }}</td>
                    <td>{{ $doc->uploader->name ?? '-' }}</td>
                    <td class="small">
                        v{{ $doc->version_no }} ·
                        @if ($doc->file)
                            <a href="{{ route('files.download', $doc->file) }}">{{ $doc->file->file_name }}</a>
                            @if ($doc->file->canPreview())
                                <a href="{{ route('files.preview', $doc->file) }}" target="_blank" class="ms-1">预览</a>
                            @endif
                        @else
                            -
                        @endif
                        <div class="text-muted">{{ $doc->file->mime_type ?? '' }} · {{ number_format(($doc->file->file_size ?? 0) / 1024, 1) }} KB</div>
                    </td>
                    <td>{{ optional($doc->created_at)->format('Y-m-d H:i') }}</td>
                    <td>
                        <form method="POST" action="{{ route('rnd.documents.audit', $doc->id) }}" class="d-flex gap-1">
                            @csrf
                            <select name="pass" class="form-select form-select-sm" style="max-width:90px">
                                <option value="1">通过</option>
                                <option value="0">驳回</option>
                            </select>
                            <input type="text" name="remark" class="form-control form-control-sm" style="max-width:180px" placeholder="审核意见">
                            <button class="btn btn-sm btn-primary">提交</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">没有待审核的交付物</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $documents->links() }}</div>
</div>
@endsection
