@extends('layouts.admin')

@section('title', '跟进记录')

@section('content')
<div class="card">
    <div class="card-header">客户跟进记录</div>
    <div class="card-body">
        <form method="POST" action="{{ route('crm.follow-ups.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    <option value="call">电话</option><option value="visit">拜访</option><option value="email">邮件</option><option value="meeting">会议</option>
                </select>
            </div>
            <div class="col-md-8"><input name="content" class="form-control form-control-sm" placeholder="跟进内容..." required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">记录</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>类型</th><th>内容</th><th>跟进人</th><th>时间</th></tr></thead>
        <tbody>
            @forelse ($items as $f)
            <tr>
                <td><span class="badge bg-info">{{ $f->type }}</span></td>
                <td>{{ $f->content }}</td>
                <td>{{ $f->user->identity_label ?? '-' }}</td>
                <td class="small">{{ $f->followed_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">暂无跟进记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
