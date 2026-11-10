@extends('layouts.admin')

@section('title', '采购订单详情')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>采购订单 {{ $order->po_no }}</span>
        <div class="d-flex gap-2">
            @can('purchase.confirm')
                @if ($order->status === 'draft')
                    <form method="POST" action="{{ route('purchase.orders.confirm', $order) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-info">确认订单</button>
                    </form>
                @endif
            @endcan
            @can('purchase.receive')
                @if (in_array($order->status, ['confirmed','received']))
                    <a href="{{ route('purchase.receive.create', $order) }}" class="btn btn-sm btn-primary">登记收货</a>
                @endif
            @endcan
            @can('purchase.confirm')
                @if (in_array($order->status, ['confirmed','received']))
                    <form method="POST" action="{{ route('purchase.orders.close', $order) }}" class="d-inline" onsubmit="return confirm('确认关闭该采购订单？')">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">关闭订单</button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('purchase.orders') }}" class="btn btn-sm btn-outline-secondary">返回</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><span class="text-muted">供应商：</span>{{ $order->supplier->supplier_name ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">采购员：</span>{{ $order->buyer->name ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">订单日期：</span>{{ $order->order_date }}</div>
            <div class="col-md-3"><span class="text-muted">预计到货：</span>{{ $order->expected_date ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">状态：</span>{{ $order->status }}</div>
            <div class="col-md-3"><span class="text-muted">总金额：</span>{{ number_format($order->total_amount, 2) }}</div>
            <div class="col-md-6"><span class="text-muted">备注：</span>{{ $order->remark ?? '-' }}</div>
        </div>

        <h6 class="fw-semibold">订单明细</h6>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>物料编码</th><th>物料名称</th><th>单位</th><th>数量</th><th>已收</th><th>单价</th><th>小计</th></tr></thead>
            <tbody>
                @foreach ($order->lines as $l)
                <tr>
                    <td class="font-monospace">{{ $l->material->material_code ?? '-' }}</td>
                    <td>{{ $l->material->name ?? '-' }}</td>
                    <td>{{ $l->material->unit ?? '' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($l->qty,2),'0'),'.') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($l->received_qty,2),'0'),'.') }}</td>
                    <td class="text-end">{{ number_format($l->unit_price,4) }}</td>
                    <td class="text-end">{{ number_format($l->qty * $l->unit_price, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h6 class="fw-semibold mt-4">收货记录</h6>
        @if ($order->receipts->isEmpty())
            <p class="text-muted small">暂无收货记录</p>
        @else
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>收货单号</th><th>收货日期</th><th>仓库</th><th>数量</th><th>状态</th><th>操作</th></tr></thead>
            <tbody>
                @foreach ($order->receipts as $r)
                <tr>
                    <td class="font-monospace">{{ $r->receipt_no }}</td>
                    <td>{{ $r->receipt_date }}</td>
                    <td>{{ $r->warehouse->name ?? '-' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($r->total_qty,2),'0'),'.') }}</td>
                    <td>{{ $r->status }}</td>
                    <td><a href="{{ route('purchase.receipts.show', $r) }}" class="btn btn-sm btn-outline-primary">查看</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
