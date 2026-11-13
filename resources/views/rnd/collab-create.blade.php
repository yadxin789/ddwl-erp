@extends('layouts.admin')

@section('title', '新建协同文档')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'collab'])

@if ($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>新建协同文档</h4>
        <p class="text-muted mb-0">项目：{{ $project->project_name }}（{{ $project->project_code }}）· 创建后直接进入 OnlyOffice 协同编辑</p>
    </div>
    <a href="{{ route('rnd.projects.collab', $project) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>返回协同办公
    </a>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-file-earmark-plus me-2"></i>从内置模板新建</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">系统内置标准 Office 模板，创建后立即进入协同编辑。</p>
                <form method="POST" action="{{ route('rnd.projects.collab.store', $project) }}">
                    @csrf
                    <input type="hidden" name="mode" value="template">
                    <div class="mb-3">
                        <label class="form-label">文档名称</label>
                        <input type="text" name="title" class="form-control" placeholder="例如：项目需求文档" maxlength="200" required>
                        @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">文档类型</label>
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <label class="btn btn-outline-primary w-100 template-option" style="cursor:pointer;">
                                    <input type="radio" name="doc_type" value="doc" class="d-none" checked>
                                    <i class="bi bi-file-earmark-word" style="font-size:28px;"></i>
                                    <div class="small mt-1">Word 文档</div>
                                </label>
                            </div>
                            <div class="col-4">
                                <label class="btn btn-outline-success w-100 template-option" style="cursor:pointer;">
                                    <input type="radio" name="doc_type" value="sheet" class="d-none">
                                    <i class="bi bi-file-earmark-spreadsheet" style="font-size:28px;"></i>
                                    <div class="small mt-1">Excel 表格</div>
                                </label>
                            </div>
                            <div class="col-4">
                                <label class="btn btn-outline-danger w-100 template-option" style="cursor:pointer;">
                                    <input type="radio" name="doc_type" value="slide" class="d-none">
                                    <i class="bi bi-file-earmark-slides" style="font-size:28px;"></i>
                                    <div class="small mt-1">PPT 演示</div>
                                </label>
                            </div>
                        </div>
                        @error('doc_type')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>创建并编辑</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-upload me-2"></i>上传现有文档</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">上传 docx / xlsx / pptx 文件（不超过 20MB），上传后即可在线协同编辑。</p>
                <form method="POST" action="{{ route('rnd.projects.collab.store', $project) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="mode" value="upload">
                    <div class="mb-3">
                        <label class="form-label">文档名称</label>
                        <input type="text" id="upload-title" name="title" class="form-control" placeholder="默认使用文件名" maxlength="200" required>
                        @error('title')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">选择文件</label>
                        <input type="file" id="upload-file" name="file" class="form-control" accept=".docx,.xlsx,.pptx" required>
                        @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-success"><i class="bi bi-upload me-1"></i>上传并编辑</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .template-option {
        border: 2px solid #ced4da !important;
        background-color: #eef1f3 !important;
        color: #6c757d !important;
        opacity: .75;
        transition: all .15s ease-in-out;
    }
    .template-option i { color: #868e96 !important; }
    .template-option:hover { transform: translateY(-1px); opacity: 1; }
    .template-option.btn-outline-primary:hover { border-color: #0d6efd !important; }
    .template-option.btn-outline-success:hover { border-color: #198754 !important; }
    .template-option.btn-outline-danger:hover { border-color: #dc3545 !important; }
    .template-option.active { opacity: 1; }
    .template-option.active.btn-outline-primary { background-color: #0d6efd !important; border-color: #0d6efd !important; color: #fff !important; box-shadow: 0 0 0 3px rgba(13,110,253,.25); }
    .template-option.active.btn-outline-success { background-color: #198754 !important; border-color: #198754 !important; color: #fff !important; box-shadow: 0 0 0 3px rgba(25,135,84,.25); }
    .template-option.active.btn-outline-danger { background-color: #dc3545 !important; border-color: #dc3545 !important; color: #fff !important; box-shadow: 0 0 0 3px rgba(220,53,69,.25); }
    .template-option.active i { color: #fff !important; }
</style>
<script>
(function () {
    var options = document.querySelectorAll('.template-option');
    function syncAll() {
        options.forEach(function (label) {
            var input = label.querySelector('input[type="radio"]');
            label.classList.toggle('active', input.checked);
        });
    }
    options.forEach(function (label) {
        label.querySelector('input[type="radio"]').addEventListener('change', syncAll);
    });
    syncAll();

    var file = document.getElementById('upload-file');
    var title = document.getElementById('upload-title');
    if (file && title) {
        file.addEventListener('change', function () {
            if (!title.value && file.files && file.files[0]) {
                title.value = file.files[0].name.replace(/\.[^.]+$/, '');
            }
        });
    }
})();
</script>
@endsection
