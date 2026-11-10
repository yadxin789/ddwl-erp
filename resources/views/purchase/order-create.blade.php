@extends('layouts.admin')

@section('title', '新建采购订单')

@section('content')
<div class="card" style="max-width: 980px;">
    <div class="card-header">新建采购订单</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
        @endif
        <form method="POST" action="{{ route('purchase.orders.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">供应商 <span class="text-danger">*</span></label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">请选择</option>
                        @foreach ($suppliers as $s) <option value="{{ $s->id }}">{{ $s->supplier_code }} {{ $s->supplier_name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">订单日期 <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">预计到货日期</label>
                    <input type="date" name="expected_date" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">备注</label>
                    <input type="text" name="remark" class="form-control">
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold">明细</span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="fa-solid fa-plus me-1"></i>添加行</button>
            </div>
            <table class="table table-sm table-bordered align-middle" id="lineTable">
                <thead class="table-light"><tr><th style="width:44%">物料</th><th style="width:18%">数量</th><th style="width:18%">单价</th><th style="width:14%">备注</th><th></th></tr></thead>
                <tbody></tbody>
            </table>

            <button class="btn btn-primary">保存订单</button>
            <a href="{{ route('purchase.orders') }}" class="btn btn-outline-secondary">返回</a>
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
        <td><input name="qty[]" type="number" step="0.01" min="0.01" class="form-control form-control-sm" required></td>
        <td><input name="unit_price[]" type="number" step="0.0001" min="0" class="form-control form-control-sm" required></td>
        <td><input name="line_remark[]" type="text" class="form-control form-control-sm"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="fa-solid fa-xmark"></i></button></td>`;
    document.querySelector('#lineTable tbody').appendChild(tr);
}
document.getElementById('addLine').addEventListener('click', addLine);
addLine();
</script>
@endsection
