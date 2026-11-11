@extends('layouts.admin')

@section('title', '系统可观测')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
        <span>失败与积压总览</span>
        <span class="text-muted small">{{ $summary['generated_at'] }}</span>
    </div>
    <div class="card-body">
        <div class="row g-2 text-center">
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">队列失败</div><div class="fs-4">{{ $summary['failed_jobs'] }}</div></div></div>
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">队列积压</div><div class="fs-4">{{ $summary['queued_jobs'] }}</div></div></div>
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Outbox 失败</div><div class="fs-4">{{ $summary['outbox_failed'] }}</div></div></div>
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">Outbox 待发</div><div class="fs-4">{{ $summary['outbox_pending'] }}</div></div></div>
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">同步失败(24h)</div><div class="fs-4">{{ $summary['sync_failed_24h'] }}</div></div></div>
            <div class="col-md-2"><div class="border rounded p-2"><div class="small text-muted">待审批</div><div class="fs-4">{{ $summary['approval_pending'] }}</div></div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">同步失败与重放</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>日志</th><th>任务</th><th>目标</th><th>状态</th><th>信息</th><th>执行时间</th><th>重放</th></tr></thead>
        <tbody>
            @forelse ($syncFailures as $row)
            <tr>
                <td class="font-monospace">#{{ $row['id'] }}</td>
                <td>{{ $row['task'] }}</td>
                <td>{{ $row['target'] ?? '-' }}</td>
                <td>
                    @if ($row['status'] === 'failed')
                    <span class="badge bg-danger">failed</span>
                    @else
                    <span class="badge bg-warning text-dark">queued</span>
                    @endif
                </td>
                <td class="small">{{ $row['message'] ?: '-' }}@if ($row['replay_of']) <span class="text-muted">（重放自 #{{ $row['replay_of'] }}）</span>@endif</td>
                <td class="small">{{ $row['run_at'] ?? '-' }}</td>
                <td>
                    @if ($row['status'] === 'failed' && ! $row['replayed_at'])
                    <form method="POST" action="{{ route('sync.logs.replay', $row['id']) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary">重放</button>
                    </form>
                    @elseif ($row['replayed_at'])
                    <span class="small text-muted">已重放 {{ $row['replayed_at'] }}</span>
                    @else
                    <span class="small text-muted">等待执行</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">近 24 小时无同步失败</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">队列失败</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>任务</th><th>队列</th><th>异常</th><th>时间</th></tr></thead>
                <tbody>
                    @forelse ($queueFailures as $row)
                    <tr><td class="small">{{ $row['name'] }}</td><td class="small">{{ $row['queue'] }}</td><td class="small text-danger">{{ $row['exception'] }}</td><td class="small">{{ $row['failed_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">无失败任务</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">Outbox 事件</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>事件</th><th>状态</th><th>重试</th><th>更新时间</th></tr></thead>
                <tbody>
                    @forelse ($outboxFailures as $row)
                    <tr><td class="small">{{ $row['event_type'] }}</td><td><span class="badge bg-secondary text-dark">{{ $row['status'] }}</span></td><td>{{ $row['retry_count'] }}</td><td class="small">{{ $row['updated_at'] }}</td></tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-3">无待发/失败事件</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">审批停留时长</div>
    <div class="card-body">
        <div class="row g-2 mb-2">
            <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">待审批单数</div><div class="fs-5">{{ $approval['pending'] }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">平均停留(小时)</div><div class="fs-5">{{ $approval['pending_avg_hours'] }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">最长停留(小时)</div><div class="fs-5">{{ $approval['pending_max_hours'] }}</div></div></div>
            <div class="col-md-3"><div class="border rounded p-2"><div class="small text-muted">超过 3 天</div><div class="fs-5 {{ $approval['pending_over_3d'] > 0 ? 'text-danger' : '' }}">{{ $approval['pending_over_3d'] }}</div></div></div>
        </div>
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>业务类型</th><th>近 30 天已决数</th><th>平均耗时(小时)</th><th>最长耗时(小时)</th></tr></thead>
            <tbody>
                @forelse ($approval['decided_by_type'] as $type => $stat)
                <tr><td>{{ $type }}</td><td>{{ $stat['count'] }}</td><td>{{ $stat['avg_hours'] }}</td><td>{{ $stat['max_hours'] }}</td></tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">近 30 天无已决审批</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">最近告警（security_events）</div>
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>级别</th><th>明细</th><th>时间</th></tr></thead>
        <tbody>
            @forelse ($alerts as $row)
            <tr><td><span class="badge {{ $row['level'] === 'critical' ? 'bg-danger' : 'bg-warning text-dark' }}">{{ \App\Support\DisplayText::level($row['level']) }}</span></td><td class="small font-monospace">{{ $row['detail'] }}</td><td class="small">{{ $row['created_at'] }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-3">无告警</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
