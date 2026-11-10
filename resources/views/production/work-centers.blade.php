@extends('layouts.admin')

@section('title', '工作中心')

@section('content')
<div class="card">
    <div class="card-header">工作中心（产线/工位）</div>
    <div class="card-body">
        <form method="POST" action="{{ route('production.work-centers.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="编码如 WC-SMT1" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="名称如 SMT一线" required></div>
            <div class="col-md-2"><input name="location" class="form-control form-control-sm" placeholder="位置">
            </div>
            <div class="col-md-2"><input name="capacity_per_day" type="number" step="0.01" class="form-control form-control-sm" placeholder="日产能"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>位置</th><th>日产能</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($centers as $c)
            <tr><td class="font-monospace">{{ $c->code }}</td><td>{{ $c->name }}</td><td>{{ $c->location ?? '-' }}</td><td>{{ $c->capacity_per_day }}</td><td>{{ $c->status }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无工作中心</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $centers->links() }}</div>
</div>
@endsection
