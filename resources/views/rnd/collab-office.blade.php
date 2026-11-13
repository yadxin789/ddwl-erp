@extends('layouts.admin')

@section('title', '协同编辑 - ' . $doc->fileName())

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'collab'])

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <a href="{{ route('rnd.projects.collab', $project) }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>返回文档列表
        </a>
        <h5 class="mb-1">
            <i class="bi {{ $doc->typeIcon() }} {{ $doc->typeColor() }} me-2"></i>{{ $doc->fileName() }}
            <span class="badge bg-light text-dark border ms-2">{{ strtoupper($doc->fileExtension()) }}</span>
            <span class="badge bg-light text-dark border">v{{ $doc->version }}</span>
        </h5>
        <div class="text-muted small">
            创建人 {{ $doc->creator->identity_label ?? '—' }} · 最后编辑 {{ $doc->updater->identity_label ?? '—' }} · {{ $doc->updated_at?->format('Y-m-d H:i') }}
        · OnlyOffice 在线编辑，保存由 DocumentServer 自动回写
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('rnd.collab.download', $doc) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i>下载
        </a>
        @if ((auth()->user()?->hasRole('role_sys_admin') ?? false) || (int) auth()->id() === (int) $project->creator_id || (int) auth()->id() === (int) $doc->creator_id)
            <form method="POST" action="{{ route('rnd.projects.collab.destroy', ['project' => $project, 'doc' => $doc]) }}" onsubmit="return confirm('确定删除文档《{{ $doc->title }}》吗？')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>删除</button>
            </form>
        @endif
    </div>
</div>

@if (!$officeEnabled)
<div class="alert alert-warning py-2">
    OnlyOffice 协同已关闭（ONLYOFFICE_ENABLED=false）。请启用后刷新页面。
</div>
@endif

<div id="office-error" class="alert alert-danger py-3 d-none">
    <strong>OnlyOffice 编辑器加载失败。</strong>
    <div class="small mt-1">
        请确认 DocumentServer 已启动并且浏览器可访问
        <code>{{ $officeServer }}</code>，同时检查 <code>ONLYOFFICE_PM_INTERNAL_URL</code>
        是 DocumentServer 能访问到本系统的地址。
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div id="placeholder" style="width:100%;height:calc(100vh - 250px);min-height:640px;"></div>
    </div>
</div>

<script type="text/javascript"
        src="{{ $officeServer }}/web-apps/apps/api/documents/api.js"
        onerror="window.onlyOfficeScriptFailed = true;"></script>
<script type="text/javascript">
(function () {
    var config = @json($config);
    var placeholder = document.getElementById('placeholder');
    var errorBox = document.getElementById('office-error');
    var started = false;

    function showError() {
        if (placeholder) {
            placeholder.innerHTML = '';
        }
        if (errorBox) {
            errorBox.classList.remove('d-none');
        }
    }

    function initEditor() {
        if (started) return;
        started = true;

        if (!window.DocsAPI || typeof window.DocsAPI.DocEditor !== 'function') {
            showError();
            return;
        }

        try {
            var editor = new DocsAPI.DocEditor('placeholder', config);
            window.addEventListener('resize', function () {
                if (editor && typeof editor.resize === 'function') {
                    try { editor.resize(); } catch (e) {}
                }
            });
        } catch (e) {
            showError();
            if (window.console) console.error(e);
        }
    }

    if (document.readyState === 'complete') {
        initEditor();
    } else {
        window.addEventListener('load', initEditor);
    }

    // API 脚本被网络/服务阻塞时给出明确提示，不留下空白页
    window.setTimeout(function () {
        if (!window.DocsAPI) showError();
    }, 6000);
})();
</script>
@endsection
