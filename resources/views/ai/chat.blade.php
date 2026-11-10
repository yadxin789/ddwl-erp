@extends('layouts.admin')

@section('title', 'AI 助手')

@section('content')
<div class="card" style="max-width:860px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>AI 对话助手 · 业务问答</span>
        @if ($selected)
            <span class="small text-muted">{{ $selected->title }} · {{ $selected->created_at?->format('m-d H:i') }}</span>
        @endif
    </div>
    <div class="card-body">
        @if ($messages->isNotEmpty())
            <div class="mb-3" style="max-height:420px; overflow-y:auto; background:#fafafa; border:1px solid #eee; border-radius:6px; padding:12px;">
                @foreach ($messages as $m)
                    <div class="mb-3">
                        <div class="small fw-semibold {{ $m->role === 'user' ? 'text-primary' : 'text-success' }}">
                            {{ $m->role === 'user' ? '我' : '助手' }}
                            <span class="text-muted fw-normal">{{ $m->created_at?->format('H:i') }}</span>
                        </div>
                        <div class="small" style="white-space:pre-wrap;">{{ $m->content }}</div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-light border small">
                业务问答只读、不调用外部大模型：答案来自当前 ERP 数据，受当前账号权限与数据范围约束，并注明统计口径。
            </div>
        @endif

        <form method="POST" action="{{ route('ai.chat.send') }}">
            @csrf
            <input type="hidden" name="conversation_id" value="{{ $selected?->id }}">
            <div class="mb-2">
                <textarea name="content" class="form-control" rows="3" id="qaContent" placeholder="输入您的问题，如：本月销售额多少？ACC-RM01 还有多少库存？" required>{{ old('content') }}</textarea>
            </div>
            <div class="d-flex flex-wrap gap-1 mb-2">
                @foreach ($examples as $example)
                    <button type="button" class="btn btn-sm btn-outline-secondary example-q">{{ $example }}</button>
                @endforeach
            </div>
            <button class="btn btn-primary">发送</button>
            @if ($errors->any())
                <span class="text-danger small ms-2">{{ $errors->first() }}</span>
            @endif
        </form>
    </div>
    <div class="card-footer">
        <h6 class="small text-muted">最近会话</h6>
        @forelse ($conversations as $c)
            <div class="small">
                <a href="{{ route('ai.chat', ['c' => $c->id]) }}" class="{{ $selected && $selected->id === $c->id ? 'fw-semibold' : '' }}">{{ $c->title }}</a>
                <span class="text-muted">{{ $c->created_at?->format('m-d H:i') }}</span>
            </div>
        @empty
            <div class="small text-muted">暂无会话</div>
        @endforelse
        {{ $conversations->links() }}
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('.example-q').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('qaContent').value = btn.textContent.trim();
            document.getElementById('qaContent').focus();
        });
    });
</script>
@endpush
