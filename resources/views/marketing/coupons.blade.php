@extends('layouts.admin')

@section('title', '优惠券')

@section('content')
<div class="card">
    <div class="card-header">优惠券</div>
    <div class="card-body">
        <form method="POST" action="{{ route('marketing.coupons.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="优惠券名称" required></div>
            <div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="面额" required></div>
            <div class="col-md-2"><input name="min_spend" type="number" step="0.01" class="form-control form-control-sm" placeholder="最低消费"></div>
            <div class="col-md-2"><input name="total_qty" type="number" class="form-control form-control-sm" placeholder="发行量"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>券号</th><th>名称</th><th>面额</th><th>最低消费</th><th>已领/总量</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($coupons as $c)
            <tr><td class="font-monospace">{{ $c->coupon_no }}</td><td>{{ $c->name }}</td><td>¥{{ number_format($c->amount,2) }}</td><td>¥{{ number_format($c->min_spend,2) }}</td><td>{{ $c->received_qty }}/{{ $c->total_qty }}</td><td>{{ $c->status ? '✅' : '❌' }}</td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无优惠券</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $coupons->links() }}</div>
</div>
@endsection
