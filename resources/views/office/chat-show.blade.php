@extends('layouts.admin')

@section('title', '聊天')

@section('content')
<div class="card" style="max-width:760px;">
    <div class="card-header d-flex justify-content-between">
        <span>{{ $room->room_name }}</span>
        <a href="{{ route('office.chat.rooms') }}" class="btn btn-sm btn-outline-secondary">返回</a>
    </div>
    <div class="card-body" style="height:360px;overflow-y:auto;">
        @forelse ($room->messages as $m)
        <div class="mb-2">
            <span class="fw-semibold">{{ $m->sender->identity_label ?? '系统' }}:</span>
            <span>{{ $m->content }}</span>
            <small class="text-muted d-block">{{ $m->created_at?->format('H:i') }}</small>
        </div>
        @empty
        <p class="text-muted text-center">暂无消息，开始聊天吧</p>
        @endforelse
    </div>
    <div class="card-footer">
        <form method="POST" action="{{ route('office.chat.send', $room) }}" class="d-flex gap-2">
            @csrf
            <input name="content" class="form-control" placeholder="输入消息..." required>
            <button class="btn btn-primary">发送</button>
        </form>
    </div>
</div>
@endsection
