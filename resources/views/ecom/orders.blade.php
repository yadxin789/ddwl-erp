@extends('layouts.admin')

@section('title', '电商订单')

@section('content')
<div class="card">
    <div class="card-header">电商订单（多平台汇总）</div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <input name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width:240px" placeholder="平台订单号">
            <select name="status" class="form-select form-select-sm" style="max-width:160px">
                <option value="">全部状态</option>
                @foreach(\App\Enums\EcomOrderStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">筛选</button>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>平台单号</th><th>平台</th><th>买家</th><th>收件人</th><th>金额</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($orders as $o)
            <tr>
                <td class="font-monospace">{{ $o->ecom_order_no }}</td>
                <td>{{ $o->platform->name ?? '-' }}</td>
                <td>{{ $o->buyer_nick ?? '-' }}</td>
                <td>{{ $o->receiver_name ?? '-' }}</td>
                <td>¥{{ number_format($o->pay_amount, 2) }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\EcomOrderStatus::tryFrom($o->status)?->label() ?? $o->status }}</span></td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无电商订单（通过同步任务拉取）</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
