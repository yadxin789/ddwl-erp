@extends('layouts.admin')

@section('title', '新建采购申请')

@section('content')
<div class="card">
    <div class="card-header">新建采购申请</div>
    <div class="card-body">
        <form method="POST" action="{{ route('purchase.requisitions.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">申请标题</label><input name="title" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">需求日期</label><input type="date" name="need_date" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">备注</label><input name="remark" class="form-control"></div>
            </div>
            <table class="table table-sm" id="lines">
                <thead class="table-light"><tr><th style="width:40%">物料</th><th>数量</th><th>单位</th><th></th></tr></thead>
                <tbody>
                    <tr>
                        <td><select name="lines[0][material_id]" class="form-select form-select-sm" required>
                            @foreach ($materials as $m) <option value="{{ $m->id }}">{{ $m->material_code }} {{ $m->name }}</option> @endforeach
                        </select></td>
                        <td><input name="lines[0][qty]" type="number" step="0.001" class="form-control form-control-sm" required></td>
                        <td><input name="lines[0][unit]" class="form-control form-control-sm" value="PCS"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">删</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow()">+ 加一行</button>
            <div class="mt-3">
                <button class="btn btn-primary">提交申请</button>
                <a href="{{ route('purchase.requisitions') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
<script>
let idx = 1;
function addRow() {
    const tbody = document.querySelector('#lines tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><select name="lines[${idx}][material_id]" class="form-select form-select-sm" required>@foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->material_code }} {{ $m->name }}</option>@endforeach</select></td>
    <td><input name="lines[${idx}][qty]" type="number" step="0.001" class="form-control form-control-sm" required></td>
    <td><input name="lines[${idx}][unit]" class="form-control form-control-sm" value="PCS"></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">删</button></td>`;
    tbody.appendChild(tr);
    idx++;
}
</script>
@endsection
