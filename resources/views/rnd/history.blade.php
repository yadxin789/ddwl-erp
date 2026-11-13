@extends('layouts.admin')

@section('title', '项目履历')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'history'])

<div class="card">
    <div class="card-header">
        <h5 class="mb-1"><i class="bi bi-clock-history me-2 text-primary"></i>项目履历</h5>
        <div class="text-muted small">项目代码：<span class="font-monospace">{{ $project->project_code }}</span></div>
    </div>
    <div class="card-body">
        @if ($logs->isEmpty())
            <div class="alert alert-info text-center mb-0">
                <i class="bi bi-info-circle me-2"></i>暂无履历记录
            </div>
        @else
            <div class="timeline">
                @foreach ($logs as $log)
                    <div class="timeline-item">
                        <div class="timeline-badge bg-{{ $log->badge() }}">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="timeline-panel">
                            <div class="timeline-heading">
                                <h6 class="timeline-title">{{ $log->action }}</h6>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ $log->created_at?->format('Y-m-d H:i:s') }}
                                    <span class="mx-2">|</span>
                                    <i class="bi bi-person me-1"></i>{{ $log->user->identity_label ?? '未知' }}
                                    @if ($log->task)
                                        <span class="mx-2">|</span><i class="bi bi-list-task me-1"></i>{{ $log->task->name }}
                                    @endif
                                </p>
                            </div>
                            @if ($log->detail)
                                <div class="alert alert-light border mb-0 py-2">
                                    <strong><i class="bi bi-file-text me-1"></i>操作内容：</strong>{{ $log->detail }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .timeline { position: relative; padding-left: 2rem; border-left: 3px solid #dee2e6; }
    .timeline-item { position: relative; margin-bottom: 2rem; }
    .timeline-badge { position: absolute; left: -2.8rem; width: 2.5rem; height: 2.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem; box-shadow: 0 2px 4px rgba(0,0,0,.1); }
    .timeline-panel { background: #f8f9fa; border-radius: .5rem; padding: 1rem 1.2rem; border: 1px solid #dee2e6; transition: all .2s ease; }
    .timeline-panel:hover { box-shadow: 0 4px 8px rgba(0,0,0,.08); transform: translateX(3px); }
    .timeline-heading { margin-bottom: .6rem; border-bottom: 1px solid #dee2e6; padding-bottom: .5rem; }
    .timeline-title { font-size: 1.05rem; font-weight: 600; margin-bottom: .35rem; color: #333; }
    @media (max-width: 575.98px) { .timeline { padding-left: 1.4rem; } .timeline-badge { left: -2rem; width: 1.8rem; height: 1.8rem; font-size: .8rem; } }
</style>
@endpush
