@extends('layouts.admin')

@section('title', '三单匹配')

@section('content')
<div class="card">
    <div class="card-header">供应商三单匹配（订单 PO · 收货 GR · 账单 Bill）</div>
    <div class="card-body">
        <form method="GET" action="{{ route('finance.three-way-match') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">全部供应商</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((int) $supplierId === (int) $s->id)>{{ $s->supplier_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">查询</button></div>
        </form>
        <div class="form-text mt-2">收货金额按"实收数量 × 采购单价"估算；差异 = 账单金额 − 收货金额。差异非 0 的订单需财务与采购核对（价格变更、退货、运费等）。</div>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr><th>采购单</th><th>供应商</th><th>订单金额</th><th>收货金额</th><th>账单金额</th><th>差异</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="font-monospace">{{ $r->po->po_no }}</td>
                    <td>{{ $r->po->supplier->supplier_name ?? '-' }}</td>
                    <td>¥{{ number_format($r->po_amount, 2) }}</td>
                    <td>¥{{ number_format($r->gr_amount, 2) }}</td>
                    <td>
                        @if ($r->bill_amount > 0)
                            ¥{{ number_format($r->bill_amount, 2) }}
                        @else
                            <span class="text-muted">未开账单</span>
                        @endif
                    </td>
                    <td class="{{ abs($r->diff) > 0.01 ? 'text-danger' : 'text-muted' }}">¥{{ number_format($r->diff, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">没有采购订单</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
