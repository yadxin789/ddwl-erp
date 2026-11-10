@extends('layouts.admin')

@section('title', '部门管理')

@section('content')
<div class="card">
    <div class="card-header">组织部门</div>
    <div class="card-body">
        <form method="POST" action="{{ route('hr.departments.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="部门编码如 D001" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="部门名称如 生产部" required></div>
            <div class="col-md-3"><input name="manager" class="form-control form-control-sm" placeholder="负责人"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>负责人</th></tr></thead>
        <tbody>
            @forelse ($deps as $d)
            <tr><td class="font-monospace">{{ $d->code }}</td><td>{{ $d->name }}</td><td>{{ $d->manager ?? '-' }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-4">暂无部门</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $deps->links() }}</div>
</div>
@endsection
