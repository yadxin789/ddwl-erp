@extends('layouts.admin')

@section('title', '项目甘特图')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'gantt'])

@php
    $statusMap = [
        'pending' => ['待开始', 'secondary'],
        'in_progress' => ['进行中', 'primary'],
        'submitted' => ['已提交待审', 'info'],
        'approved' => ['已通过', 'success'],
        'rejected' => ['已驳回', 'danger'],
        'overdue' => ['已逾期', 'warning'],
        'completed' => ['已完成', 'success'],
        'cancelled' => ['已取消', 'dark'],
    ];
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            甘特图 · {{ $tasks->count() }} 个任务 · 时间轴 {{ $rangeStart->format('Y-m-d') }} ~ {{ $rangeEnd->format('Y-m-d') }}（{{ $totalDays }} 天）
        </span>
        <span class="text-muted small">条形按任务实际起止日期排布，部门间串行；红色竖线为今天</span>
    </div>
    <div class="card-body p-0">
        <div class="gantt-wrap">
            {{-- 月份刻度头 --}}
            <div class="gantt-head">
                <div class="gantt-left gantt-th">任务 / 执行人</div>
                <div class="gantt-right">
                    <div class="gantt-months">
                        @foreach ($months as $m)
                        <div class="gantt-month" style="width: {{ $m['width'] }}%;">
                            <span>{{ $m['label'] }}</span>
                        </div>
                        @endforeach
                    </div>
                    @if ($todayOffset !== null)
                    <div class="gantt-today-line" style="left: {{ round($todayOffset / $totalDays * 100, 4) }}%;"></div>
                    @endif
                </div>
            </div>

            @forelse ($tasks as $t)
            @php
                $s = $t->start_date ?: $project->start_date ?: $rangeStart;
                $e = $t->end_date ?: $s;
                $offset = max(0, (int) $rangeStart->diffInDays(\Carbon\Carbon::parse($s)->startOfDay()));
                $span = max(1, (int) \Carbon\Carbon::parse($s)->startOfDay()->diffInDays(\Carbon\Carbon::parse($e)->startOfDay()) + 1);
                $left = $offset / $totalDays * 100;
                $width = $span / $totalDays * 100;
                [$label, $badge] = $statusMap[$t->status] ?? [$t->status, 'secondary'];
            @endphp
            <div class="gantt-row">
                <div class="gantt-left">
                    <div class="fw-semibold text-truncate" title="{{ $t->name }}">{{ $t->name }}</div>
                    <div class="text-muted small text-truncate">
                        {{ $t->taskType->name ?? '—' }}
                        · {{ $t->executor->identity_label ?? '未指定' }}
                        · {{ \Carbon\Carbon::parse($s)->format('m-d') }} ~ {{ \Carbon\Carbon::parse($e)->format('m-d') }}
                        @if ($t->is_overdue)<span class="badge bg-danger ms-1">逾期</span>@endif
                    </div>
                </div>
                <div class="gantt-right">
                    <div class="gantt-grid">
                        @foreach ($months as $m)
                        <div class="gantt-grid-cell" style="width: {{ $m['width'] }}%;"></div>
                        @endforeach
                    </div>
                    <div class="gantt-bar bg-{{ $badge }}" style="left: {{ round($left, 4) }}%; width: {{ round($width, 4) }}%;"
                         title="{{ $t->name }}｜{{ $label }}｜{{ \Carbon\Carbon::parse($s)->format('Y-m-d') }} ~ {{ \Carbon\Carbon::parse($e)->format('Y-m-d') }}">
                        <span>{{ $span }}天</span>
                    </div>
                    @if ($todayOffset !== null)
                    <div class="gantt-today-line" style="left: {{ round($todayOffset / $totalDays * 100, 4) }}%;"></div>
                    @endif
                </div>
            </div>
            @empty
            <div class="p-4 text-center text-muted">该项目还没有任务，甘特图暂无内容</div>
            @endforelse
        </div>
    </div>
</div>

@push('styles')
<style>
    .gantt-wrap { overflow-x: auto; }
    .gantt-head, .gantt-row { display: flex; align-items: stretch; border-bottom: 1px solid #eef0f3; }
    .gantt-head { background: #f8f9fa; border-bottom: 2px solid #dee2e6; position: sticky; top: 0; z-index: 2; }
    .gantt-left { width: 260px; min-width: 260px; padding: 8px 12px; border-right: 1px solid #eef0f3; }
    .gantt-th { font-weight: 600; }
    .gantt-right { flex: 1; position: relative; min-height: 44px; }
    .gantt-months { display: flex; height: 32px; }
    .gantt-month { border-right: 1px dashed #dee2e6; display: flex; align-items: center; padding-left: 6px; font-size: 12px; color: #6c757d; overflow: hidden; white-space: nowrap; }
    .gantt-grid { position: absolute; inset: 0; display: flex; }
    .gantt-grid-cell { border-right: 1px dashed #f1f3f5; height: 100%; }
    .gantt-bar { position: absolute; top: 12px; height: 20px; border-radius: 4px; color: #fff; font-size: 11px; line-height: 20px; padding: 0 6px; white-space: nowrap; overflow: hidden; opacity: .92; }
    .gantt-today-line { position: absolute; top: 0; bottom: 0; width: 2px; background: rgba(220, 53, 69, .75); z-index: 1; }
    .gantt-today-line::after { content: '今天'; position: absolute; top: -18px; left: -10px; font-size: 10px; color: #dc3545; }
</style>
@endpush
@endsection
