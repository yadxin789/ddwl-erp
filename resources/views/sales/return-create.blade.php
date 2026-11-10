@extends('layouts.admin')

@section('title', '新建退货单')

@section('content')
<div class="card" style="max-width: 980px;">
    <div class="card-header">新建客户退货单（创建后库存状态转 returned，待质检）</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
        @endif
        <form method="POST" action="{{ route('sales.returns.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">客户 <span class="text-danger">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">请选择</option>
                        @foreach ($customers as $c) <option value="{{ $c->id }}">{{ $c->customer_name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">原销售订单</label>
                    <select name="sales_order_id" class="form-select">
                        <option value="">无</option>
                        @foreach ($orders as $o) <option value="{{ $o->id }}">{{ $o->order_no }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">退货原因</label>
                    <input type="text" name="return_reason" class="form-control">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">退货明细</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="fa-solid fa-plus me-1"></i>添加行</button>
            </div>
            <table class="table table-sm table-bordered align-middle" id="lineTable">
                <thead class="table-light"><tr><th style="width:44%">物料</th><th style="width:22%">批次号</th><th style="width:22%">退回数量</th><th></th></tr></thead>
                <tbody></tbody>
            </table>

            <button class="btn btn-primary">创建退货单</button>
            <a href="{{ route('sales.returns') }}" class="btn btn-outline-secondary">返回</a>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
const opts = `@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->material_code }} {{ $m->name }}</option>@endforeach`;
function addLine() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select name="material_id[]" class="form-select form-select-sm" required>${opts}</select></td>
        <td><input name="lot_no[]" type="text" class="form-control form-control-sm" placeholder="LOT20261101"></td>
        <td><input name="qty[]" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>`;
    document.querySelector('#lineTable tbody').appendChild(tr);
}
document.getElementById('addLine').addEventListener('click', addLine);
addLine();
</script>
@endsection
