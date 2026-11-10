@extends('layouts.admin')

@section('title', '电商平台')

@section('content')
<div class="card">
    <div class="card-header">电商平台账号</div>
    <div class="card-body">
        <form method="POST" action="{{ route('ecom.platforms.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="编码 taobao/jd" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="平台名称" required></div>
            <div class="col-md-4"><input name="shop_url" class="form-control form-control-sm" placeholder="店铺URL"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>店铺</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($platforms as $p)
            <tr><td class="font-monospace">{{ $p->code }}</td><td>{{ $p->name }}</td><td class="small">{{ $p->shop_url ?? '-' }}</td><td>{{ $p->status ? '✅' : '❌' }}</td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">暂无平台</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $platforms->links() }}</div>
</div>
@endsection
