@php
    /**
     * 项目详情页标签（对齐参考目录 project/partials/project-tabs）：
     * 概览 / 甘特图 / 项目履历 / 协同办公 / 聊天室 / 电话会议
     * 未实现的分支先渲染为禁用态，避免点出 404。
     */
    $activeTab = $activeTab ?? 'overview';
    $tabs = [
        ['key' => 'overview', 'label' => '项目概览', 'icon' => 'bi-speedometer2', 'route' => route('rnd.projects.show', $project), 'ready' => true],
        ['key' => 'gantt', 'label' => '甘特图', 'icon' => 'bi-bar-chart-steps', 'route' => route('rnd.projects.gantt', $project), 'ready' => true],
        ['key' => 'history', 'label' => '项目履历', 'icon' => 'bi-clock-history', 'route' => route('rnd.projects.history', $project), 'ready' => true],
        ['key' => 'collab', 'label' => '协同办公', 'icon' => 'bi-people', 'route' => route('rnd.projects.collab', $project), 'ready' => true],
        ['key' => 'chat', 'label' => '聊天室', 'icon' => 'bi-chat-dots', 'route' => route('rnd.projects.chat', $project), 'ready' => true],
        ['key' => 'voice', 'label' => '电话会议', 'icon' => 'bi-telephone', 'route' => route('rnd.projects.voice', $project), 'ready' => true],
    ];
@endphp

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
    <div>
        <h4 class="mb-0">
            <i class="bi bi-folder2-open text-primary me-2"></i>{{ $project->project_name }}
        </h4>
        <span class="text-muted small">
            项目编码：<span class="font-monospace">{{ $project->project_code }}</span>
            · 状态：
            <span class="badge bg-{{ $project->statusBadge() }}">{{ $project->statusLabel() }}</span>
            @if ($project->isOverdue())
                <span class="badge bg-danger ms-1">已超计划 {{ $project->overdueDays() }} 天</span>
            @endif
        </span>
    </div>
    <div class="d-flex align-items-center gap-2">
        @can('manage', $project)
        <form method="POST" action="{{ route('rnd.projects.status', $project) }}" class="d-flex gap-1">
            @csrf
            <select name="status" class="form-select form-select-sm" style="width:110px" onchange="this.form.submit()">
                @foreach (\App\Models\Rnd\RndProject::STATUSES as $k => $v)
                <option value="{{ $k }}" @selected($project->status === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </form>
        @endcan
        <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">返回列表</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    @foreach ($tabs as $tab)
    <li class="nav-item">
        @if ($tab['ready'] && $tab['route'])
        <a class="nav-link {{ $activeTab === $tab['key'] ? 'active fw-semibold' : '' }}" href="{{ $tab['route'] }}">
            <i class="bi {{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
        </a>
        @else
        <span class="nav-link disabled" title="待接入" style="cursor:not-allowed;">
            <i class="bi {{ $tab['icon'] }} me-1"></i>{{ $tab['label'] }}
        </span>
        @endif
    </li>
    @endforeach
</ul>
