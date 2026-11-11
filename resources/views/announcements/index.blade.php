@extends('layouts.admin')

@section('title', '公告通知')

@section('content')
@can('announcement.manage')
<div class="card mb-3">
    <div class="card-header">发布公告</div>
    <form method="POST" action="{{ route('announcement.store') }}" class="card-body row g-2">
        @csrf
        <div class="col-md-6">
            <input type="text" name="title" class="form-control form-control-sm" required maxlength="120" placeholder="标题">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select form-select-sm">
                <option value="general">一般</option>
                <option value="policy">制度</option>
                <option value="notice">通知</option>
                <option value="urgent">紧急</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-sm btn-primary w-100">发布</button>
        </div>
        <div class="col-12">
            <textarea name="content" class="form-control form-control-sm" rows="3" required maxlength="5000" placeholder="正文"></textarea>
        </div>
    </form>
</div>
@endcan

<div class="card">
    <div class="card-header">公告（{{ $announcements->total() }}）</div>
    <div class="card-body p-0">
        @forelse ($announcements as $item)
            <div class="border-bottom p-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <span class="badge bg-secondary">{{ \App\Support\DisplayText::type($item->category) }}</span>
                        <span class="fw-bold ms-1">{{ $item->title }}</span>
                    </div>
                    <div class="small text-muted">
                        {{ $item->published_at?->format('Y-m-d H:i') ?? '-' }}
                        <span class="ms-2">{{ $item->publisher->identity_label ?? '-' }}</span>
                        @can('announcement.manage')
                            <form method="POST" action="{{ route('announcement.toggle', $item) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-link text-danger p-0 ms-2">下架</button>
                            </form>
                        @endcan
                    </div>
                </div>
                <div class="small text-muted mt-1" style="white-space:pre-wrap;">{{ $item->content }}</div>
            </div>
        @empty
            <div class="text-center text-muted py-4">暂无公告</div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $announcements->links() }}</div>
@endsection
