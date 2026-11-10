@extends('layouts.admin')

@section('title', '销售订单详情')

@section('content')
@if ($errors->any())
    <div class="alert alert-danger py-2">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
@endif

@if (session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if ($shortageTotal > 0)
    <div class="alert alert-warning py-2">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong>库存不足：需生产 {{ rtrim(rtrim(number_format($shortageTotal, 2), '0'), '.') }} 件</strong>
        @foreach ($shortages as $code => $qty)（{{ $code }}: {{ rtrim(rtrim(number_format($qty, 2), '0'), '.') }}）@endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>订单 {{ $order->order_no }}</span>
                <span>
                    @php $st = ['draft'=>'草稿','confirmed'=>'已确认','picking'=>'发货中','shipped'=>'已发货','closed'=>'已关闭','cancelled'=>'已取消'][$order->status] ?? $order->status; @endphp
                    <span class="badge bg-{{ $order->status === 'draft' ? 'warning' : ($order->status === 'confirmed' ? 'info' : ($order->status === 'shipped' ? 'success' : 'secondary')) }} text-dark">{{ $st }}</span>
                </span>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">客户</dt><dd class="col-sm-9">{{ $order->customer->customer_name ?? '-' }}</dd>
                    <dt class="col-sm-3">订单日期</dt><dd class="col-sm-9">{{ $order->order_date }}</dd>
                    <dt class="col-sm-3">交货日期</dt><dd class="col-sm-9">{{ $order->delivery_date ?? '-' }}</dd>
                    <dt class="col-sm-3">总金额</dt><dd class="col-sm-9">{{ number_format($order->total_amount, 2) }}</dd>
                    <dt class="col-sm-3">已发货</dt><dd class="col-sm-9">{{ rtrim(rtrim(number_format($order->shipped_qty, 2), '0'), '.') }}</dd>
                    <dt class="col-sm-3">备注</dt><dd class="col-sm-9">{{ $order->remark ?? '-' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">订单明细</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>料号</th><th>名称</th><th>数量</th><th>单价</th><th>已发</th><th>小计</th></tr></thead>
                    <tbody>
                        @foreach ($order->lines as $line)
                        <tr>
                            <td class="font-monospace">{{ $line->material->material_code ?? '-' }}</td>
                            <td>{{ $line->material->name ?? '-' }}</td>
                            <td>{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                            <td class="text-end">{{ number_format($line->unit_price, 4) }}</td>
                            <td>{{ rtrim(rtrim(number_format($line->shipped_qty, 2), '0'), '.') }}</td>
                            <td class="text-end">{{ number_format($line->qty * $line->unit_price, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">操作</div>
            <div class="card-body">
                @can('sales.confirm')
                @if ($order->status === 'draft')
                <form method="POST" action="{{ route('sales.orders.confirm', $order) }}">
                    @csrf
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-check me-1"></i>确认订单（预留库存）</button>
                </form>
                @endif
                @endcan

                @can('sales.ship')
                @if (in_array($order->status, ['confirmed', 'picking']))
                <form method="POST" action="{{ route('sales.orders.ship', $order) }}" class="mt-3">
                    @csrf
                    <div class="fw-semibold mb-2">出货登记</div>
                    <table class="table table-sm mb-2">
                        <thead><tr><th>料号</th><th>剩余</th><th style="width:90px">本次出货</th></tr></thead>
                        <tbody>
                            @foreach ($order->lines as $line)
                            <tr>
                                <td class="small">{{ $line->material->material_code ?? '' }}</td>
                                <td class="small">{{ rtrim(rtrim(number_format($line->qty - $line->shipped_qty, 2), '0'), '.') }}</td>
                                <td><input type="number" name="ship_qty[{{ $line->id }}]" class="form-control form-control-sm" min="0" step="0.01" value="0"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small mb-0">出货日期</label><input type="date" name="shipment_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required></div>
                        <div class="col-6"><label class="form-label small mb-0">物流公司</label><input type="text" name="logistics_company" class="form-control form-control-sm"></div>
                        <div class="col-12"><label class="form-label small mb-0">物流单号</label><input type="text" name="tracking_no" class="form-control form-control-sm"></div>
                    </div>
                    <button class="btn btn-success w-100"><i class="fa-solid fa-truck me-1"></i>创建出货单</button>
                </form>
                @endif
                @endcan

                @if ($order->shipments->isNotEmpty())
                <div class="mt-3">
                    <div class="fw-semibold mb-2">出货记录</div>
                    @foreach ($order->shipments as $s)
                    <div class="border rounded p-2 mb-2 small">
                        <div class="font-monospace">{{ $s->shipment_no }} <span class="badge bg-{{ $s->status === 'shipped' ? 'success' : 'secondary' }}">{{ $s->status }}</span></div>
                        <div class="text-muted">{{ $s->shipment_date }} 数量 {{ rtrim(rtrim(number_format($s->total_qty, 2), '0'), '.') }}</div>
                        @if ($s->logistics_company) <div>{{ $s->logistics_company }} / {{ $s->tracking_no }}</div> @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
