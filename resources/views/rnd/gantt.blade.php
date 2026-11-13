@extends('layouts.admin')

@section('title', '项目甘特图')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'gantt'])

@php
    $statusMap = [
        'pending' => ['待开始', '#6c757d'],
        'in_progress' => ['进行中', '#17a2b8'],
        'submitted' => ['已提交待审', '#ffc107'],
        'approved' => ['已通过', '#28a745'],
        'completed' => ['已完成', '#28a745'],
        'rejected' => ['已驳回', '#dc3545'],
        'overdue' => ['已逾期', '#dc3545'],
        'cancelled' => ['已取消', '#6c757d'],
    ];
    $monthColors = ['#e8f4fd', '#fef3c7', '#d1fae5', '#fce7f3', '#f3e8ff', '#fed7aa'];
    $tasksByDept = $tasks->groupBy(fn ($t) => (int) ($t->department_id ?? 0));
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>甘特图</h5>
        <span class="text-muted small">{{ $tasks->count() }} 个任务 · 时间轴 {{ $rangeStart->format('Y-m-d') }} ~ {{ $rangeEnd->format('Y-m-d') }}（{{ $totalDays }} 天）</span>
    </div>
    <div class="card-body">
        <div class="gantt-scroll-container">
            <div class="gantt-container">
                <div class="gantt-header">
                    <div class="gantt-task-column">任务名称</div>
                    <div class="gantt-timeline-header">
                        <div class="gantt-months">
                            <div class="month-legend-title">月份标识:</div>
                            @foreach ($months as $index => $month)
                                <div class="month-legend-item" style="background-color: {{ $monthColors[$index % count($monthColors)] }}">
                                    <span>{{ $month['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="gantt-dates">
                            @for ($i = 0; $i < $totalDays; $i++)
                                @php
                                    $date = $rangeStart->copy()->addDays($i);
                                    $monthKey = $date->format('Y-m');
                                    $monthIndex = 0;
                                    foreach ($months as $mi => $mm) {
                                        if ($mm['label'] === $date->format('Y年n月')) { $monthIndex = $mi; break; }
                                    }
                                @endphp
                                <div class="gantt-date {{ $date->isWeekend() ? 'weekend' : '' }}"
                                     style="background-color: {{ $monthColors[$monthIndex % count($monthColors)] }}"
                                     title="{{ $date->format('Y-m-d') }}">
                                    <span class="date-day">{{ $date->format('d') }}</span>
                                    <span class="date-weekday">{{ $date->translatedFormat('D') }}</span>
                                </div>
                            @endfor
                        </div>
                    </div>
                </div>

                <div class="gantt-body">
                    @forelse ($departments as $dept)
                        @php $deptTasks = $tasksByDept->get($dept['id'], collect()); @endphp
                        @if ($deptTasks->isNotEmpty())
                            <div class="gantt-row gantt-dept-header">
                                <div class="gantt-task-column">
                                    <i class="bi bi-building me-1"></i>{{ $dept['name'] }}
                                    <span class="badge bg-primary ms-2">{{ $deptTasks->count() }} 个任务</span>
                                </div>
                                <div class="gantt-timeline">
                                    <div class="gantt-grid">
                                        @for ($i = 0; $i < $totalDays; $i++)
                                            <div class="gantt-grid-cell" style="background-color: #eff6ff;"></div>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                            @foreach ($deptTasks as $task)
                                @php
                                    $taskStart = $task->start_date ? $task->start_date->copy()->startOfDay() : $rangeStart->copy();
                                    $taskEnd = $task->end_date ? $task->end_date->copy()->startOfDay() : $taskStart->copy();
                                    $taskStartOffset = (int) $rangeStart->diffInDays($taskStart, false);
                                    $taskDuration = max(1, (int) $taskStart->diffInDays($taskEnd, false) + 1);
                                    if ($taskStartOffset < 0) {
                                        $taskDuration += $taskStartOffset;
                                        $taskStartOffset = 0;
                                    }
                                    $taskDuration = max(1, min($taskDuration, $totalDays - $taskStartOffset));
                                    [$statusLabel, $statusColor] = $statusMap[$task->status] ?? [$task->status, '#6c757d'];
                                @endphp
                                <div class="gantt-row">
                                    <div class="gantt-task-column">
                                        <div class="task-info">
                                            <strong class="task-name" title="{{ $task->name }}">{{ $task->name }}</strong>
                                            <div class="task-meta">
                                                <span class="task-assignee"><i class="bi bi-person me-1"></i>{{ $task->executor->identity_label ?? '未分配' }}</span>
                                                <span class="task-status" style="background-color: {{ $statusColor }}">{{ $statusLabel }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="gantt-timeline">
                                        <div class="gantt-grid">
                                            @for ($i = 0; $i < $totalDays; $i++)
                                                <div class="gantt-grid-cell"></div>
                                            @endfor
                                        </div>
                                        <div class="gantt-bar"
                                             style="left: {{ ($taskStartOffset / $totalDays) * 100 }}%; width: {{ ($taskDuration / $totalDays) * 100 }}%; background-color: {{ $statusColor }}"
                                             title="任务: {{ $task->name }} | 负责人: {{ $task->executor->identity_label ?? '未分配' }} | 状态: {{ $statusLabel }} | 起始: {{ $task->start_date ? $task->start_date->format('Y-m-d') : '未设置' }} | 截止: {{ $task->end_date ? $task->end_date->format('Y-m-d') : '未设置' }}">
                                            <div class="gantt-bar-content">{{ $task->name }}</div>
                                            <div class="gantt-bar-start">●</div>
                                            <div class="gantt-bar-end">●</div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    @empty
                        <div class="p-4 text-center text-muted">该项目还没有任务，甘特图暂无内容</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">图例说明</div>
            <div class="card-body">
                <div class="legend-container">
                    <div class="legend-item"><div class="legend-color" style="background-color: #6c757d"></div><span>等待 / 已取消</span></div>
                    <div class="legend-item"><div class="legend-color" style="background-color: #17a2b8"></div><span>进行中</span></div>
                    <div class="legend-item"><div class="legend-color" style="background-color: #ffc107"></div><span>等待审核</span></div>
                    <div class="legend-item"><div class="legend-color" style="background-color: #28a745"></div><span>已完成 / 已通过</span></div>
                    <div class="legend-item"><div class="legend-color" style="background-color: #dc3545"></div><span>已驳回 / 已逾期</span></div>
                </div>
            </div>
        </div>

        <div class="mt-3 alert alert-info mb-0">
            <strong>项目时间范围：</strong>{{ $project->start_date?->format('Y年m月d日') ?? '未设置' }} 至 {{ $project->end_date?->format('Y年m月d日') ?? '未设置' }}
            <span class="ms-4"><strong>总天数：</strong>{{ $totalDays }} 天</span>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .gantt-scroll-container { overflow-x: auto; border: 1px solid #dee2e6; border-radius: .25rem; }
    .gantt-container { min-width: max-content; background: #fff; }
    .gantt-header { display: flex; background: #4a5568; color: #fff; position: sticky; top: 0; z-index: 10; }
    .gantt-task-column { width: 250px; min-width: 250px; padding: 10px 14px; border-right: 2px solid #2d3748; font-weight: 600; font-size: 14px; }
    .gantt-timeline-header { flex: 1; min-width: 0; }
    .gantt-months { display: flex; align-items: center; gap: 10px; background: #2d3748; padding: 7px 12px; }
    .month-legend-title { font-size: 12px; font-weight: 600; color: #a0aec0; white-space: nowrap; }
    .month-legend-item { padding: 4px 10px; border-radius: 4px; border: 1px solid rgba(255,255,255,.1); }
    .month-legend-item span { font-size: 11px; font-weight: 500; color: #2d3748; white-space: nowrap; }
    .gantt-dates { display: flex; border-bottom: 2px solid #2d3748; }
    .gantt-date { width: 35px; min-width: 35px; padding: 7px 2px; text-align: center; border-right: 1px solid rgba(0,0,0,.06); }
    .gantt-date.weekend { opacity: .72; }
    .date-day { display: block; font-size: 13px; font-weight: 500; color: #4a5568; }
    .date-weekday { display: block; font-size: 10px; color: #718096; }
    .gantt-body { max-height: 500px; overflow-y: auto; }
    .gantt-row { display: flex; border-bottom: 1px solid #e2e8f0; min-height: 50px; align-items: center; }
    .gantt-row:hover { background: #f7fafc; }
    .gantt-dept-header { min-height: 40px; border-bottom: 2px solid #93c5fd; }
    .gantt-dept-header .gantt-task-column { font-size: 14px; padding: 8px 14px; color: #1a56db; background: #eff6ff; }
    .gantt-row:nth-child(even) { background: #fff; }
    .gantt-row:nth-child(odd) { background: #fafafa; }
    .gantt-row .gantt-task-column { border-right: 1px solid #e2e8f0; color: #2d3748; }
    .task-info { display: flex; flex-direction: column; }
    .task-name { font-size: 13px; color: #2d3748; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .task-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .task-assignee { font-size: 11px; color: #718096; }
    .task-status { font-size: 10px; padding: 2px 6px; border-radius: 3px; color: #fff; }
    .gantt-timeline { flex: 1; position: relative; min-height: 50px; padding: 5px 0; min-width: 0; }
    .gantt-grid { display: flex; position: absolute; inset: 0; }
    .gantt-grid-cell { flex: 1; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; }
    .gantt-bar { position: absolute; top: 50%; transform: translateY(-50%); border-radius: 4px; padding: 6px 8px; min-height: 28px; display: flex; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,.1); cursor: pointer; }
    .gantt-bar:hover { transform: translateY(-50%) scale(1.02); box-shadow: 0 4px 8px rgba(0,0,0,.15); }
    .gantt-bar-content { color: #fff; font-size: 11px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
    .gantt-bar-start, .gantt-bar-end { font-size: 8px; color: rgba(255,255,255,.8); margin: 0 2px; }
    .legend-container { display: flex; flex-wrap: wrap; gap: 15px; }
    .legend-item { display: flex; align-items: center; gap: 8px; }
    .legend-color { width: 20px; height: 12px; border-radius: 3px; }
</style>
@endpush
