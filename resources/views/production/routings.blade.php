@extends('layouts.admin')

@section('title', '工艺路线')

@section('content')
<div class="card">
    <div class="card-header">工艺路线</div>
    <div class="card-body">
        <form method="POST" action="{{ route('production.routings.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="routing_code" class="form-control form-control-sm" placeholder="路由编码 RT-001" required></div>
            <div class="col-md-4">
                <select name="product_material_id" class="form-select form-select-sm" required>
                    @foreach ($products as $p) <option value="{{ $p->id }}">{{ $p->material_code }} {{ $p->name }}</option> @endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="version" class="form-control form-control-sm" placeholder="版本 V1.0"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>路由编码</th><th>产品</th><th>版本</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($routings as $r)
            <tr><td class="font-monospace">{{ $r->routing_code }}</td><td>{{ $r->product->material_code ?? '' }} {{ $r->product->name ?? '' }}</td><td>{{ $r->version }}</td><td>{{ \App\Support\DisplayText::status($r->status) }}</td></tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted py-4">暂无工艺路线</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $routings->links() }}</div>
</div>
@endsection
