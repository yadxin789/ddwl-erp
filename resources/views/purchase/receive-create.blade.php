@extends('layouts.admin')

@section('title', '采购收货登记')

@section('content')
<div class="card" style="max-width: 980px;">
    <div class="card-header">收货登记 — 采购单 {{ $order->po_no }}</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
        @endif
        <form method="POST" action="{{ route('purchase.receive.store') }}">
            @csrf
            <input type="hidden" name="po_id" value="{{ $order->id }}">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">收货日期 <span class="text-danger">*</span></label>
                    <input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">入库仓库 <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">请选择</option>
                        @foreach ($warehouses as $w) <option value="{{ $w->id }}">{{ $w->warehouse_code }} {{ $w->name }}</option> @endforeach
                    </select>
                </div>
            </div>

            <p class="small text-muted">收货后库存默认进入 <span class="badge bg-warning text-dark">qc_hold 待质检</span> 状态，IQC 合格后转 available。</p>

            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light"><tr><th>物料</th><th>批次号/Date Code</th><th>收货数量</th><th>供应商批次</th></tr></thead>
                <tbody>
                    @foreach ($order->lines as $l)
                    <tr>
                        <td>
                            <input type="hidden" name="material_id[]" value="{{ $l->material_id }}">
                            {{ $l->material->material_code ?? '' }} {{ $l->material->name ?? '' }}
                        </td>
                        <td><input type="text" name="lot_no[]" class="form-control form-control-sm" required placeholder="如 2542"></td>
                        <td><input type="number" name="qty[]" step="0.01" min="0" class="form-control form-control-sm" placeholder="0"></td>
                        <td><input type="text" name="supplier_batch[]" class="form-control form-control-sm"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <button class="btn btn-primary">提交收货</button>
            <a href="{{ route('purchase.orders.show', $order) }}" class="btn btn-outline-secondary">返回</a>
        </form>
    </div>
</div>
@endsection
