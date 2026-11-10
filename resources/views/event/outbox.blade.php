@extends('layouts.admin')

@section('title', 'Outbox事件')

@section('content')
<div class="card">
    <div class="card-header">Outbox 出站事件（待发布到外部系统）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>事件类型</th><th>关联</th><th>状态</th><th>重试</th><th>发布时间</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr>
                <td>{{ $r->event_type }}</td>
                <td class="small">{{ $r->ref_type }}#{{ $r->ref_id }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\OutboxStatus::tryFrom($r->status)?->label() ?? $r->status }}</span></td>
                <td>{{ $r->retry_count }}</td>
                <td class="small">{{ $r->published_at?->format('m-d H:i') ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无出站事件</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
