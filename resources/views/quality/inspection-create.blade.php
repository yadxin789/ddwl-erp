@extends('layouts.admin')

@section('title', '新建检验')

@section('content')
<div class="card" style="max-width:720px">
    <div class="card-header">新建检验记录</div>
    <div class="card-body">
        <form method="POST" action="{{ route('quality.inspections.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">检验阶段</label>
                @if ($locked && $stage)
                    <input type="hidden" name="stage" value="{{ $stage }}">
                    <input class="form-control" value="{{ $stage }}（本岗位锁定，不可切换）" disabled>
                @else
                    <select name="stage" class="form-select" required>
                        <option value="IQC">IQC 来料检验</option>
                        <option value="IPQC">IPQC 制程检验</option>
                        <option value="FQC">FQC 成品检验</option>
                        <option value="OQC">OQC 出货检验</option>
                    </select>
                @endif
            </div>
            <div class="mb-3">
                <label class="form-label">物料</label>
                <select name="material_id" class="form-select" required>
                    @foreach ($materials as $m)<option value="{{ $m->id }}">{{ $m->material_code }} {{ $m->name }}</option>@endforeach
                </select>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label">抽检数量</label><input name="sample_qty" type="number" step="0.01" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">合格数量</label><input name="qualified_qty" type="number" step="0.01" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">不良数量</label><input name="defect_qty" type="number" step="0.01" class="form-control" required></div>
            </div>
            <div class="mb-3"><label class="form-label">备注</label><textarea name="remark" class="form-control" rows="2"></textarea></div>
            <button class="btn btn-primary">提交检验</button>
            <a href="{{ route('quality.inspections') }}" class="btn btn-outline-secondary">返回</a>
        </form>
    </div>
</div>
@endsection
