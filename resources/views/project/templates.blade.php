@extends('layouts.admin')

@section('title', '项目模板管理')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">项目模板管理</h1>
    <a href="{{ route('project.templates.create') }}" class="btn btn-primary btn-sm">
        <i class="fa fa-plus me-1"></i>创建项目模板
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($templates->isEmpty())
            <p class="text-center text-muted py-4 mb-0">暂无项目模板</p>
        @else
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>项目模板名称</th>
                        <th>描述</th>
                        <th>任务配置</th>
                        <th>创建人</th>
                        <th>创建时间</th>
                        <th style="width:170px;">操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td>{{ $template->description ?: '-' }}</td>
                            <td>
                                <span class="badge bg-info me-1 mb-1">{{ count($template->taskConfigs()) }} 个任务</span>
                                @foreach($template->taskTypes() as $type)
                                    <span class="badge bg-secondary me-1 mb-1">{{ $type->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $template->user->identity_label ?? '-' }}</td>
                            <td>{{ $template->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('project.templates.show', $template) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-eye me-1"></i>详情
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal" data-bs-target="#deleteTemplateModal"
                                        data-url="{{ route('project.templates.destroy', $template) }}"
                                        data-name="{{ $template->name }}">
                                    <i class="fa fa-trash me-1"></i>删除
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="deleteTemplateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">删除项目模板</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="deleteTemplateForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    确定删除模板「<strong id="deleteTemplateName"></strong>」吗？删除后不可恢复。
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-danger">确认删除</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('deleteTemplateModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;
        document.getElementById('deleteTemplateForm').action = button.dataset.url;
        document.getElementById('deleteTemplateName').textContent = button.dataset.name || '';
    });
</script>
@endsection
