@extends('layouts.admin')

@section('title', '新建领料申请')

@section('content')
<div class="card">
    <div class="card-header">新建领料申请（按数量申请，不设金额上限）</div>
    <form method="POST" action="{{ route('production.issue-requests.store') }}" class="card-body">
        @csrf
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">工单号</label>
                <select name="production_order_id" class="form-select" required>
                    <option value="">请选择</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->order_no }}（计划 {{ $order->plan_qty }}）</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-7">
                <label class="form-label">用途</label>
                <input type="text" name="purpose" class="form-control" maxlength="200" required placeholder="如：工单首件试产 / 补料">
            </div>
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="text-muted small">明细：超出该工单 BOM 标准用量的行会标"超耗"，提交后需生产主管审批。</span>
        </div>

        <table class="table table-sm align-middle" id="lineTable">
            <thead class="table-light"><tr><th>物料</th><th style="width:180px;">申请数量</th><th style="width:60px;"></th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <select name="material_id[]" class="form-select form-select-sm" required>
                            <option value="">请选择物料</option>
                            @foreach ($materials as $material)
                                <option value="{{ $material->id }}">{{ $material->material_code }} · {{ $material->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0.01" name="qty[]" class="form-control form-control-sm" required></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">删除</button></td>
                </tr>
            </tbody>
        </table>

        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()">+ 添加一行</button>
        <div class="mt-3">
            <button class="btn btn-primary">提交申请</button>
            <a href="{{ route('production.issue-requests') }}" class="btn btn-link">返回</a>
        </div>
    </form>
</div>

<script>
function addLine() {
    const tbody = document.querySelector('#lineTable tbody');
    const row = tbody.rows[0].cloneNode(true);
    row.querySelectorAll('input').forEach(i => i.value = '');
    row.querySelectorAll('select').forEach(s => s.selectedIndex = 0);
    tbody.appendChild(row);
}
</script>
@endsection
