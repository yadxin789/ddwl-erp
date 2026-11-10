@extends('layouts.admin')

@section('title', 'AI 助手')

@section('content')
<div class="card" style="max-width:760px;">
    <div class="card-header">AI 对话助手</div>
    <div class="card-body">
        <form method="POST" action="{{ route('ai.chat.send') }}">
            @csrf
            <div class="mb-3">
                <textarea name="content" class="form-control" rows="3" placeholder="输入您的问题，如：查一下电子料当前库存" required></textarea>
            </div>
            <button class="btn btn-primary">发送</button>
        </form>
    </div>
    <div class="card-footer">
        <h6 class="small text-muted">最近会话</h6>
        @forelse ($conversations as $c)
        <div class="small">{{ $c->title }} <span class="text-muted">{{ $c->created_at?->format('m-d H:i') }}</span></div>
        @empty
        <div class="small text-muted">暂无会话</div>
        @endforelse
    </div>
</div>
@endsection
