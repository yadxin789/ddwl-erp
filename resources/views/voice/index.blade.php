@extends('layouts.admin')

@section('title', '语音会议')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>语音会议室</span>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">创建会议室</button>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>房间号</th><th>名称</th><th>主持人</th><th>状态</th><th>开始时间</th></tr></thead>
        <tbody>
            @forelse ($rooms as $r)
            <tr><td class="font-monospace">{{ $r->room_no }}</td><td>{{ $r->name }}</td><td>{{ $r->host->name ?? '-' }}</td><td><span class="badge bg-secondary text-dark">{{ \App\Enums\VoiceRoomStatus::tryFrom($r->status)?->label() ?? $r->status }}</span></td><td>{{ $r->started_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无会议室（WebRTC 待接入）</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('voice.rooms.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">创建语音会议室</h5></div>
            <div class="modal-body">
                <input name="name" class="form-control" placeholder="会议名称" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button class="btn btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>
@endsection
