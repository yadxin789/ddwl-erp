@extends('layouts.admin')

@section('title', '促销活动')

@section('content')
<div class="card">
    <div class="card-header">促销活动</div>
    <div class="card-body">
        <form method="POST" action="{{ route('marketing.promotions.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="活动编码 P001" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="活动名称" required></div>
            <div class="col-md-2">
                <select name="type" class="form-select form-select-sm">
                    @foreach(\App\Enums\PromotionType::cases() as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="value" type="number" step="0.01" class="form-control form-control-sm" placeholder="数值" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>类型</th><th>值</th><th>启用</th></tr></thead>
        <tbody>
            @forelse ($promos as $p)
            <tr><td class="font-monospace">{{ $p->code }}</td><td>{{ $p->name }}</td><td>{{ \App\Enums\PromotionType::tryFrom($p->type)?->label() ?? $p->type }}</td><td>{{ $p->value }}</td><td>{{ $p->status ? '✅' : '❌' }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无促销活动</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $promos->links() }}</div>
</div>
@endsection
