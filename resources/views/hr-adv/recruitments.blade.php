@extends('layouts.admin')

@section('title', '招聘需求')

@section('content')
<div class="card">
    <div class="card-header">招聘需求管理</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hra.recruitments.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><input name="position" class="form-control form-control-sm" placeholder="招聘岗位" required></div>
            <div class="col-md-1"><input name="headcount" type="number" class="form-control form-control-sm" value="1" required></div>
            <div class="col-md-2"><input name="department" class="form-control form-control-sm" placeholder="需求部门"></div>
            <div class="col-md-4"><input name="requirement" class="form-control form-control-sm" placeholder="岗位要求"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">发布</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>需求号</th><th>岗位</th><th>人数</th><th>部门</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr><td class="font-monospace">{{ $r->req_no }}</td><td>{{ $r->position }}</td><td>{{ $r->headcount }}</td><td>{{ $r->department ?? '-' }}</td><td>{{ \App\Enums\RecruitmentStatus::tryFrom($r->status)?->label() ?? $r->status }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无招聘需求</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
