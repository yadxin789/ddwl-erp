@extends('layouts.admin')

@section('title', '项目履历')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'history'])

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>项目履历（共 {{ $logs->total() }} 条）</span>
        <span class="text-muted small">记录创建、派工、交付、审核、阶段推进、状态变更与延期</span>
    </div>
    <div class="card-body">
        @if ($logs->isEmpty())
        <div class="alert alert-info mb-0">该项目还没有履历记录</div>
        @else
        <div class="timeline">
            @foreach ($logs as $log)
            <div class="timeline-item">
                <div class="timeline-dot bg-{{ $log->badge() }}"></div>
                <div class="timeline-body">
                    <div class="d-flex justify-content-between flex-wrap gap-1">
                        <div class="fw-semibold">{{ $log->action }}</div>
                        <div class="text-muted small">{{ $log->created_at?->format('Y-m-d H:i:s') }}</div>
                    </div>
                    @if ($log->detail)
                    <div class="mt-1">{{ $log->detail }}</div>
                    @endif
                    <div class="text-muted small mt-1">
                        操作人：{{ $log->user->identity_label ?? '—' }}
                        @if ($log->task) · 任务：{{ $log->task->name }} @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-3">{{ $logs->links() }}</div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .timeline { position: relative; padding-left: 22px; border-left: 2px solid #e9ecef; }
    .timeline-item { position: relative; margin-bottom: 18px; }
    .timeline-dot { position: absolute; left: -31px; top: 6px; width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 0 3px #fff; }
    .timeline-body { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 10px 14px; }
</style>
@endpush
@endsection
