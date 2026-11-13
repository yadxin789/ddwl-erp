@extends('layouts.admin')

@section('title', '新建协作文档')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'collab'])

<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('rnd.projects.collab', $project) }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>返回文档列表
    </a>
    <h4 class="mb-0"><i class="bi bi-plus-square me-2 text-primary"></i>新建文档</h4>
</div>

@if ($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="card">
    <div class="card-header">
        项目：{{ $project->project_name }}（<span class="font-monospace">{{ $project->project_code }}</span>）
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('rnd.projects.collab.store', $project) }}">
            @csrf
            <div class="mb-4">
                <label class="form-label fw-semibold">文档名称</label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" maxlength="200" required placeholder="例如：项目周会纪要">
            </div>

            <div class="mb-3 fw-semibold">选择文档类型</div>
            <div class="row g-3">
                @foreach ($docTypes as $key => $type)
                    <div class="col-sm-6 col-lg-3">
                        <label class="card h-100 doc-type-card {{ old('doc_type', 'doc') === $key ? 'border-primary shadow-sm' : '' }}">
                            <input type="radio" name="doc_type" value="{{ $key }}" class="d-none doc-type-input" @checked(old('doc_type', 'doc') === $key)>
                            <div class="card-body text-center py-4">
                                <i class="bi {{ $type['icon'] }} {{ $type['color'] }}" style="font-size:38px;"></i>
                                <div class="fw-semibold mt-2">{{ $type['label'] }}</div>
                                <div class="text-muted small">.{{ $type['ext'] }}</div>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            <div class="alert alert-info mt-4 mb-3 py-2 small">
                新建文档会写入一份对应的初始模板，创建后进入协同编辑；正文保存在本机数据库，断网可用。
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>创建并编辑</button>
                <a href="{{ route('rnd.projects.collab', $project) }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.doc-type-card').forEach(function (card) {
    card.addEventListener('click', function () {
        document.querySelectorAll('.doc-type-card').forEach(function (c) {
            c.classList.remove('border-primary', 'shadow-sm');
        });
        card.classList.add('border-primary', 'shadow-sm');
        card.querySelector('.doc-type-input').checked = true;
    });
});
</script>
@endpush
