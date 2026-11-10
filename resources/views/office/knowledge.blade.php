@extends('layouts.admin')

@section('title', '知识库')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>企业知识库</span>
        <form method="POST" action="{{ route('office.knowledge.upload') }}" enctype="multipart/form-data" class="d-inline">
            @csrf
            <label class="btn btn-primary btn-sm mb-0">上传文档
                <input type="file" name="file" class="d-none" required onchange="this.form.submit()">
            </label>
        </form>
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2 mb-3">
            <input name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="搜索文件名...">
            <button class="btn btn-sm btn-outline-secondary">搜索</button>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>文件名</th><th>大小</th><th>类型</th><th>上传时间</th></tr></thead>
        <tbody>
            @forelse ($files as $f)
            <tr>
                <td><i class="fa-solid fa-file me-2"></i>{{ $f->file_name }}</td>
                <td>{{ number_format($f->file_size/1024, 1) }} KB</td>
                <td><span class="badge bg-light text-dark">{{ $f->mime_type }}</span></td>
                <td class="small">{{ $f->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">知识库暂无文档，点击右上角上传</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $files->links() }}</div>
</div>
@endsection
