@extends('layouts.admin')

@section('title', '研发项目')

@section('content')
@php
    $tabs = [
        'all' => '全部',
        'initiation' => '立项中',
        'in_progress' => '进行中',
        'paused' => '已暂停',
        'completed' => '已完成',
    ];
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-primary h-100"><div class="card-body py-2">
            <div class="text-muted small">进行中</div>
            <div class="fs-4 fw-bold text-primary">{{ $counts['in_progress'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-secondary h-100"><div class="card-body py-2">
            <div class="text-muted small">立项中</div>
            <div class="fs-4 fw-bold text-secondary">{{ $counts['initiation'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-warning h-100"><div class="card-body py-2">
            <div class="text-muted small">已暂停</div>
            <div class="fs-4 fw-bold text-warning">{{ $counts['paused'] }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-success h-100"><div class="card-body py-2">
            <div class="text-muted small">已完成</div>
            <div class="fs-4 fw-bold text-success">{{ $counts['completed'] }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <ul class="nav nav-pills nav-sm">
                @foreach ($tabs as $key => $label)
                <li class="nav-item">
                    <a class="nav-link py-1 px-3 {{ $status === ($key === 'all' ? '' : $key) ? 'active' : '' }}"
                       href="{{ route('rnd.projects', $key === 'all' ? [] : ['status' => $key]) }}">
                        {{ $label }}
                        <span class="badge bg-light text-dark ms-1">{{ $counts[$key] }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
            <div class="d-flex align-items-center gap-2">
                @if ($overdueCount > 0)
                <span class="badge bg-danger">已过计划结束日 {{ $overdueCount }} 个</span>
                @endif
                @can('rnd.project.create')
                <a href="{{ route('rnd.projects.create') }}" class="btn btn-primary btn-sm">新建项目</a>
                @endcan
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>项目编号</th>
                    <th>项目名称</th>
                    <th>型号 / 版本</th>
                    <th>负责人</th>
                    <th>参与人</th>
                    <th>进度</th>
                    <th>状态</th>
                    <th>计划日期</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($projects as $p)
                <tr>
                    <td class="font-monospace">{{ $p->project_code }}</td>
                    <td>
                        <a href="{{ route('rnd.projects.show', $p) }}" class="text-decoration-none">{{ $p->project_name }}</a>
                        @if ($p->product_name)<div class="text-muted small">{{ $p->product_name }}</div>@endif
                    </td>
                    <td>{{ $p->product_model ?? '-' }}<span class="text-muted small"> / {{ $p->version }}</span></td>
                    <td>{{ $p->creator->identity_label ?? '-' }}</td>
                    <td><span class="badge bg-light text-dark">{{ count($p->participantIds()) }} 人</span></td>
                    <td>
                        <div class="progress" style="height:18px;width:110px;">
                            <div class="progress-bar" style="width:{{ $p->progress }}%">{{ $p->progress }}%</div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-{{ $p->statusBadge() }}">{{ $p->statusLabel() }}</span>
                        @if ($p->isOverdue())
                        <span class="badge bg-danger ms-1" title="已超过计划结束日 {{ $p->overdueDays() }} 天">超期 {{ $p->overdueDays() }} 天</span>
                        @endif
                    </td>
                    <td class="small">{{ $p->start_date?->format('Y-m-d') }} ~ {{ $p->end_date?->format('Y-m-d') }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="{{ route('rnd.projects.show', $p) }}" class="btn btn-sm btn-outline-primary">详情</a>
                            @can('manage', $p)
                            <form method="POST" action="{{ route('rnd.projects.status', $p) }}" class="d-flex gap-1">
                                @csrf
                                <select name="status" class="form-select form-select-sm" style="width:100px" onchange="this.form.submit()">
                                    @foreach (\App\Models\Rnd\RndProject::STATUSES as $k => $v)
                                    <option value="{{ $k }}" @selected($p->status === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">暂无研发项目</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $projects->links() }}</div>
</div>
@endsection
