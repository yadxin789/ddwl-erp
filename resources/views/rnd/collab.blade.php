@extends('layouts.admin')

@section('title', '项目协同办公')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'collab'])

@if (session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if ($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

@if ($current)
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <a href="{{ route('rnd.projects.collab', $project) }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left me-1"></i>返回文档列表
            </a>
            <h5 class="mb-1">
                <i class="bi {{ $current->typeIcon() }} {{ $current->typeColor() }} me-2"></i>{{ $current->title }}
                <span class="badge bg-light text-dark border ms-2">{{ $current->typeLabel() }}</span>
                <span class="badge bg-light text-dark border">v{{ $current->version }}</span>
            </h5>
            <div class="text-muted small">
                创建人 {{ $current->creator->identity_label ?? '—' }} · 最后编辑 {{ $current->updater->identity_label ?? '—' }} · {{ $current->updated_at?->format('Y-m-d H:i') }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('rnd.collab.download', $current) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i>下载
            </a>
            @if ((auth()->user()?->hasRole('role_sys_admin') ?? false) || (int) auth()->id() === (int) $project->creator_id || (int) auth()->id() === (int) $current->creator_id)
                <form method="POST" action="{{ route('rnd.projects.collab.destroy', ['project' => $project, 'doc' => $current]) }}" onsubmit="return confirm('确定删除文档《{{ $current->title }}》吗？')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>删除</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>文档编辑</span>
            <span class="text-muted small">正文保存在本机数据库，断网可编辑、可保存，保存后版本号自动递增</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('rnd.collab.save', $current) }}">
                @csrf
                <div class="d-flex gap-2 mb-2">
                    <input type="text" name="title" class="form-control" value="{{ $current->title }}" maxlength="200" required>
                    <button class="btn btn-primary text-nowrap"><i class="bi bi-check2 me-1"></i>保存</button>
                </div>
                <textarea name="content" class="form-control font-monospace" rows="20" style="font-size:13px"
                          placeholder="在此编写协同内容（会议纪要、方案、评审结论…）">{{ $current->content }}</textarea>
                <div class="text-muted small mt-2">
                    当前版本 v{{ $current->version }} · 最后修改 {{ $current->updater->identity_label ?? '—' }} {{ $current->updated_at?->format('Y-m-d H:i') }}
                    · 每次保存版本号 +1，供项目履历与评审追溯
                </div>
            </form>
        </div>
    </div>
@else
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1"><i class="bi bi-people me-2 text-primary"></i>协同办公</h4>
            <p class="text-muted mb-0">项目：{{ $project->project_name }}（{{ $project->project_code }}）· 项目组成员可共同编辑文档</p>
        </div>
        <a href="{{ route('rnd.projects.collab.create', $project) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>新建文档
        </a>
    </div>

    @if ($docs->isEmpty())
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-earmark-text" style="font-size:48px;color:#c5c9d4;"></i>
                <p class="text-muted mt-3 mb-0">该项目还没有协作文档，点击右上角“新建文档”开始协同编辑。</p>
            </div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>文档名称</th>
                            <th>类型</th>
                            <th>大小</th>
                            <th>版本</th>
                            <th>创建人</th>
                            <th>最后编辑人</th>
                            <th>更新时间</th>
                            <th class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($docs as $doc)
                            <tr>
                                <td>
                                    <i class="bi {{ $doc->typeIcon() }} {{ $doc->typeColor() }} me-2"></i>
                                    <a href="{{ route('rnd.projects.collab', ['project' => $project, 'doc' => $doc]) }}">{{ $doc->title }}</a>
                                    <div class="text-muted small">{{ $doc->excerpt(48) }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $doc->typeLabel() }}</span></td>
                                <td>{{ $doc->displaySize() }}</td>
                                <td>v{{ $doc->version }}</td>
                                <td>{{ $doc->creator->name ?? '—' }}</td>
                                <td>{{ $doc->updater->name ?? '—' }}</td>
                                <td>{{ $doc->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('rnd.projects.collab', ['project' => $project, 'doc' => $doc]) }}" class="btn btn-sm btn-outline-primary" title="编辑">
                                        <i class="bi bi-pencil"></i><span class="d-none d-lg-inline ms-1">编辑</span>
                                    </a>
                                    <a href="{{ route('rnd.collab.download', $doc) }}" class="btn btn-sm btn-outline-secondary" title="下载">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    @if ((auth()->user()?->hasRole('role_sys_admin') ?? false) || (int) auth()->id() === (int) $project->creator_id || (int) auth()->id() === (int) $doc->creator_id)
                                        <form method="POST" action="{{ route('rnd.projects.collab.destroy', ['project' => $project, 'doc' => $doc]) }}" class="d-inline" onsubmit="return confirm('确定删除文档《{{ $doc->title }}》吗？')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="删除"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
