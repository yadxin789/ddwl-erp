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
                                @foreach($template->taskTypes() as $type)
                                    <span class="badge bg-secondary me-1 mb-1">{{ $type->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $template->user->name ?? '-' }}</td>
                            <td>{{ $template->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('rnd.projects.create', ['template' => $template->id]) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-plus me-1"></i>用此模板建项目
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
