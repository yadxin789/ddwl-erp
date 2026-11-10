@extends('layouts.admin')

@section('title', '聊天室')

@section('content')
<div class="card">
    <div class="card-header">聊天室</div>
    <div class="list-group list-group-flush">
        @forelse ($rooms as $r)
        <a href="{{ route('office.chat.show', $r) }}" class="list-group-item list-group-item-action">
            <i class="fa-solid fa-comments me-2"></i>{{ $r->room_name }} <small class="text-muted">（{{ $r->room_type }}）</small>
        </a>
        @empty
        <div class="list-group-item text-center text-muted py-4">暂无聊天室</div>
        @endforelse
    </div>
    <div class="card-footer">{{ $rooms->links() }}</div>
</div>
@endsection
