@extends('layouts.admin')

@section('title', '采购申请')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>采购申请单</span>
        @if (app(\App\Support\NavigationService::class)->canOpenRoute(auth()->user(), 'purchase.requisitions.create'))
            <a href="{{ route('purchase.requisitions.create') }}" class="btn btn-primary btn-sm">新建申请</a>
        @endif
    </div>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>申请号</th><th>标题</th><th>申请人</th><th>需求日期</th><th>状态</th><th>创建时间</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr>
                <td class="font-monospace">{{ $r->req_no }}</td>
                <td>{{ $r->title }}</td>
                <td>{{ $r->requester->identity_label ?? '-' }}</td>
                <td>{{ $r->need_date }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Support\DisplayText::status($r->status) }}</span></td>
                <td class="small">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无采购申请</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
