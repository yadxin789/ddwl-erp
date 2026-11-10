@extends('layouts.admin')

@section('title', '支援中心')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">支援待办（{{ $todos->count() }}）</div>
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light"><tr><th>事项</th><th>类型</th><th>截止</th></tr></thead>
                <tbody>
                @forelse ($todos as $todo)
                    <tr>
                        <td class="small">{{ $todo->title }}</td>
                        <td class="small text-muted">{{ $todo->todo_type }}</td>
                        <td class="small">{{ $todo->deadline?->format('Y-m-d') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted py-4">暂无支援待办</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">最新公告</div>
            <div class="card-body p-0">
                @forelse ($announcements as $item)
                    <div class="border-bottom p-2 small">
                        <div class="fw-bold">{{ $item->title }}</div>
                        <div class="text-muted">{{ $item->published_at?->format('Y-m-d') }}</div>
                    </div>
                @empty
                    <div class="text-center text-muted py-3">暂无公告</div>
                @endforelse
            </div>
            <div class="card-footer small">
                <a href="{{ route('announcement.index') }}">查看全部公告 →</a>
            </div>
        </div>
    </div>
</div>
@endsection
