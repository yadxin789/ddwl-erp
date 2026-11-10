@extends('layouts.admin')

@section('title', '研发项目')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>研发项目（共 {{ $projects->total() }} 个）</span>
        @can('rnd.create')
        <a href="{{ route('rnd.projects.create') }}" class="btn btn-primary btn-sm">新建项目</a>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>项目编号</th><th>项目名称</th><th>型号</th><th>负责人</th><th>进度</th><th>状态</th><th>计划日期</th><th>操作</th></tr></thead>
            <tbody>
                @php $smap = ['in_progress'=>'进行中','overdue'=>'已逾期','completed'=>'已完成','paused'=>'已暂停']; @endphp
                @forelse ($projects as $p)
                <tr>
                    <td class="font-monospace">{{ $p->project_code }}</td>
                    <td>{{ $p->project_name }}</td>
                    <td>{{ $p->product_model ?? '-' }}</td>
                    <td>{{ $p->creator->name ?? '-' }}</td>
                    <td>
                        <div class="progress" style="height:18px;width:100px;">
                            <div class="progress-bar" style="width:{{ $p->progress }}%">{{ $p->progress }}%</div>
                        </div>
                    </td>
                    <td><span class="badge bg-secondary text-dark">{{ $smap[$p->status] ?? $p->status }}</span></td>
                    <td>{{ $p->start_date }} ~ {{ $p->end_date }}</td>
                    <td><a href="{{ route('rnd.projects.show', $p) }}" class="btn btn-sm btn-outline-primary">详情</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">暂无研发项目</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $projects->links() }}</div>
</div>
@endsection
