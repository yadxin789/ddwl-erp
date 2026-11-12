@extends('layouts.admin')

@section('title', '项目协同办公')

@section('content')
@include('rnd.partials.project-tabs', ['activeTab' => 'collab'])

@if (session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if ($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>协同文档（{{ $docs->count() }}）</span>
            </div>
            <div class="list-group list-group-flush" style="max-height:420px;overflow-y:auto;">
                @forelse ($docs as $d)
                <a href="{{ route('rnd.projects.collab', ['project' => $project->id, 'doc' => $d->id]) }}"
                   class="list-group-item list-group-item-action {{ $current && $current->id === $d->id ? 'active' : '' }}">
                    <div class="fw-semibold text-truncate">{{ $d->title }}</div>
                    <div class="small {{ $current && $current->id === $d->id ? 'text-white-50' : 'text-muted' }}">
                        v{{ $d->version }} · {{ $d->updater->identity_label ?? ($d->creator->identity_label ?? '—') }}
                        · {{ $d->updated_at?->format('m-d H:i') }}
                    </div>
                </a>
                @empty
                <div class="list-group-item text-muted small">还没有协同文档</div>
                @endforelse
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('rnd.projects.collab.store', $project) }}" class="d-flex gap-1">
                    @csrf
                    <input type="text" name="title" class="form-control form-control-sm" placeholder="新文档标题" required maxlength="200">
                    <button class="btn btn-sm btn-primary">新建</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>文档编辑</span>
                <span class="text-muted small">正文保存在本机数据库，断网可编辑、可保存</span>
            </div>
            @if ($current)
            <div class="card-body">
                <form method="POST" action="{{ route('rnd.collab.save', $current->id) }}">
                    @csrf
                    <div class="d-flex gap-2 mb-2">
                        <input type="text" name="title" class="form-control" value="{{ $current->title }}" maxlength="200" required>
                        <button class="btn btn-primary text-nowrap">保存</button>
                    </div>
                    <textarea name="content" class="form-control font-monospace" rows="18" style="font-size:13px"
                              placeholder="在此编写协同内容（会议纪要、方案、评审结论…）">{{ $current->content }}</textarea>
                    <div class="text-muted small mt-2">
                        当前版本 v{{ $current->version }} · 最后修改
                        {{ $current->updater->identity_label ?? '—' }}
                        {{ $current->updated_at?->format('Y-m-d H:i') }}
                        · 每次保存版本号 +1，供项目履历与评审追溯
                    </div>
                </form>
            </div>
            @else
            <div class="card-body d-flex align-items-center justify-content-center text-muted">
                先新建一份协同文档
            </div>
            @endif
            <div class="card-footer small text-muted">
                可选的 OnlyOffice 增强：部署本地 DocumentServer 后，在 <code>config/collab.php</code> 配置
                <code>doc_server_url</code>，本页即可切换为多人实时协同编辑；未部署时使用上方内置编辑器，功能不缺失。
            </div>
        </div>
    </div>
</div>
@endsection
