@extends('layouts.admin')

@section('title', '商品SPU')

@section('content')
<div class="card">
    <div class="card-header">电商商品 SPU</div>
    <div class="card-body">
        <form method="POST" action="{{ route('product.spus.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="商品名称 *" required></div>
                <div class="col-md-2">
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">类目</option>
                        @foreach ($cats as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="brand_id" class="form-select form-select-sm">
                        <option value="">品牌</option>
                        @foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2"><input name="sale_price" type="number" step="0.01" class="form-control form-control-sm" placeholder="售价" required></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建SPU</button></div>
            </div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>SPU编号</th><th>名称</th><th>类目</th><th>品牌</th><th>售价</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($spus as $s)
            <tr>
                <td class="font-monospace">{{ $s->spu_no }}</td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->category->name ?? '-' }}</td>
                <td>{{ $s->brand->name ?? '-' }}</td>
                <td>¥{{ number_format($s->sale_price, 2) }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\ProductSpuStatus::tryFrom($s->status)?->label() ?? $s->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无商品</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $spus->links() }}</div>
</div>
@endsection
