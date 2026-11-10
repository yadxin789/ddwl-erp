@extends('layouts.admin')

@section('title', '发货作业')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small">本页只处理"已确认 / 拣货中"订单的发货登记，库存扣减与出货单在提交时自动完成。</div>
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="订单号">
        <button class="btn btn-sm btn-outline-secondary">查询</button>
    </form>
</div>

@forelse ($orders as $order)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="font-monospace fw-bold">{{ $order->order_no }}</span>
            <span class="text-muted ms-2">{{ $order->customer->customer_name ?? '-' }}</span>
            <span class="badge bg-secondary text-dark ms-2">{{ $order->status }}</span>
        </div>
        <div class="text-muted small">交期：{{ $order->delivery_date?->format('Y-m-d') ?? '-' }}</div>
    </div>

    <form method="POST" action="{{ route('sales.orders.ship', $order) }}">
        @csrf
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>物料</th><th class="text-end">订单数量</th><th class="text-end">已发</th><th class="text-end">待发</th><th style="width:140px;">本次发货</th></tr>
            </thead>
            <tbody>
                @foreach ($order->lines as $line)
                    @php $remaining = (float) $line->qty - (float) $line->shipped_qty; @endphp
                    <tr>
                        <td>{{ $line->material->material_code ?? '-' }} · {{ $line->material->name ?? '-' }}</td>
                        <td class="text-end">{{ number_format((float) $line->qty, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $line->shipped_qty, 2) }}</td>
                        <td class="text-end">{{ number_format($remaining, 2) }}</td>
                        <td>
                            <input type="number" step="0.01" min="0" max="{{ $remaining }}"
                                   name="ship_qty[{{ $line->id }}]" value="{{ $remaining }}"
                                   class="form-control form-control-sm">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small mb-1">发货日期</label>
                <input type="date" name="shipment_date" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required>
            </div>
            <div>
                <label class="form-label small mb-1">物流公司</label>
                <input type="text" name="logistics_company" class="form-control form-control-sm" maxlength="64">
            </div>
            <div>
                <label class="form-label small mb-1">运单号</label>
                <input type="text" name="tracking_no" class="form-control form-control-sm" maxlength="64">
            </div>
            <button class="btn btn-primary btn-sm ms-auto">登记出库</button>
        </div>
    </form>
</div>
@empty
<div class="card"><div class="card-body text-center text-muted py-5">暂无待发货订单</div></div>
@endforelse

{{ $orders->links() }}
@endsection
