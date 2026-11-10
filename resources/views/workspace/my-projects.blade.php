@extends('layouts.admin')

@section('title', '我的项目列表')

@section('content')
<div class="text-muted small mb-3">只列出本人创建、或本人担任任务执行人的研发项目。</div>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr><th>项目编号</th><th>项目名称</th><th>机型</th><th>创建人</th><th>周期</th><th class="text-end">进度</th><th>状态</th></tr>
        </thead>
        <tbody>
            @forelse ($projects as $project)
                <tr>
                    <td class="font-monospace">
                        @if (Route::has('rnd.projects.show'))
                            <a href="{{ route('rnd.projects.show', $project) }}">{{ $project->project_code }}</a>
                        @else
                            {{ $project->project_code }}
                        @endif
                    </td>
                    <td>{{ $project->project_name }}</td>
                    <td class="text-muted small">{{ $project->product_model ?: '-' }}</td>
                    <td class="small">{{ $project->creator->name ?? '-' }}</td>
                    <td class="small text-muted">
                        {{ $project->start_date?->format('Y-m-d') ?? '-' }} ~ {{ $project->end_date?->format('Y-m-d') ?? '-' }}
                    </td>
                    <td class="text-end">{{ (int) $project->progress }}%</td>
                    <td><span class="badge bg-secondary text-dark">{{ $project->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">暂无参与的项目</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $projects->links() }}</div>
</div>
@endsection
