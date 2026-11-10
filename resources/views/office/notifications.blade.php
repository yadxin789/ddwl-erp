@extends('layouts.admin')

@section('title', '我的通知')

@section('content')
<div class="card">
    <div class="card-header">消息通知</div>
    <ul class="list-group list-group-flush">
        @forelse ($items as $n)
        <li class="list-group-item">
            <div class="d-flex justify-content-between">
                <span class="{{ $n->is_read ? '' : 'fw-semibold' }}">{{ $n->title }}</span>
                <small class="text-muted">{{ $n->created_at?->format('m-d H:i') }}</small>
            </div>
            @if($n->content)<div class="small text-muted">{{ $n->content }}</div>@endif
        </li>
        @empty
        <li class="list-group-item text-center text-muted py-4">暂无通知</li>
        @endforelse
    </ul>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
