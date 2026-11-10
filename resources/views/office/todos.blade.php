@extends('layouts.admin')

@section('title', '我的待办')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>待办事项</span>
        <a href="{{ route('office.todos', ['pending' => 1]) }}" class="btn btn-sm btn-outline-secondary">只看未完成</a>
    </div>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>类型</th><th>事项</th><th>截止</th><th>状态</th><th></th></tr></thead>
        <tbody>
            @forelse ($items as $t)
            <tr class="{{ $t->deadline && !$t->is_finished && $t->deadline->isPast() ? 'table-danger' : '' }}">
                <td><span class="badge bg-light text-dark">{{ $t->todo_type }}</span></td>
                <td>{{ $t->title }}</td>
                <td>{{ $t->deadline?->format('Y-m-d H:i') ?? '-' }}</td>
                <td>{{ $t->is_finished ? '已完成' : '待处理' }}</td>
                <td>
                    @unless($t->is_finished)
                    <form method="POST" action="{{ route('office.todos.finish', $t) }}">@csrf
                        <button class="btn btn-sm btn-outline-success">完成</button>
                    </form>
                    @endunless
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无待办</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
