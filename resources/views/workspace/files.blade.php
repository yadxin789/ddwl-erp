@extends('layouts.admin')

@section('title', '资料文件')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>资料文件</span>
                <form class="d-flex gap-2" method="GET">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="文件名">
                    <button class="btn btn-sm btn-outline-secondary">查询</button>
                </form>
            </div>
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>文件名</th><th class="text-end">大小</th><th>上传人</th><th>上传时间</th></tr></thead>
                <tbody>
                    @forelse ($files as $file)
                        <tr>
                            <td>
                                <a href="{{ route('files.download', $file) }}">{{ $file->file_name }}</a>
                                @if ($file->canPreview())
                                    <a href="{{ route('files.preview', $file) }}" target="_blank" class="ms-1">预览</a>
                                @endif
                            </td>
                            <td class="text-end small text-muted">{{ number_format($file->file_size / 1024, 1) }} KB</td>
                            <td class="small">{{ $file->uploader->identity_label ?? '-' }}</td>
                            <td class="small text-muted">{{ $file->created_at?->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-5">暂无资料文件</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-footer">{{ $files->links() }}</div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">上传资料</div>
            <div class="card-body">
                @can('file.resource.manage')
                <form method="POST" action="{{ route('file.resources.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="file" class="form-control mb-2" required>
                    <div class="form-text mb-2">可上传任意格式（最大 20MB）；图片、Excel、PDF、Office 可直接在线预览。</div>
                    <button class="btn btn-primary w-100">上传</button>
                </form>
                @else
                    <div class="text-muted small">当前岗位无上传权限，可下载已有资料。</div>
                @endcan
            </div>
        </div>
    </div>
</div>
@endsection
