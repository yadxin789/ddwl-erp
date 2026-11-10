@extends('layouts.admin')

@section('title', '商品类目')

@section('content')
<div class="card">
    <div class="card-header">商品类目</div>
    <div class="card-body">
        <form method="POST" action="{{ route('product.categories.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><input name="code" class="form-control form-control-sm" placeholder="类目编码 C001" required></div>
            <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="类目名称 如 手机配件" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>排序</th></tr></thead>
        <tbody>
            @forelse ($cats as $c)
            <tr><td class="font-monospace">{{ $c->code }}</td><td>{{ $c->name }}</td><td>{{ $c->sort }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-4">暂无类目</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $cats->links() }}</div>
</div>
@endsection
