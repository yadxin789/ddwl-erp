@extends('layouts.admin')

@section('title', '项目模板')

@section('content')
<div class="card mb-3"><div class="card-header">新建模板</div>
    <form method="POST" action="{{ route('project.templates.store') }}" class="card-body row g-2">
        @csrf
        <div class="col-md-4"><input type="text" name="name" class="form-control form-control-sm" required maxlength="128" placeholder="模板名称（如 消费电子新品）"></div>
        <div class="col-md-3">
            <select name="task_type_ids[]" class="form-select form-select-sm" multiple size="3" required>
                @foreach ($taskTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建模板</button></div>
    </form>
</div>

<div class="card"><div class="card-header">模板列表（{{ $templates->count() }}）</div>
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th>模板</th><th>任务类型</th><th style="width:340px;">按模板建项目</th></tr></thead>
        <tbody>
        @forelse ($templates as $template)
            <tr>
                <td class="fw-bold small">{{ $template->name }}<div class="text-muted font-monospace">{{ $template->code }}</div></td>
                <td class="small">
                    @foreach ($template->taskTypes() as $type)
                        <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $type->name }}</span>
                    @endforeach
                </td>
                <td>
                    <form method="POST" action="{{ route('project.templates.apply', $template) }}" class="d-flex gap-1">
                        @csrf
                        <input type="text" name="project_name" class="form-control form-control-sm" required placeholder="项目名称">
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required style="width:150px;">
                        <button class="btn btn-sm btn-outline-primary">建项目</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-center text-muted py-4">暂无模板</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
