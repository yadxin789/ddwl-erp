@extends('layouts.admin')

@section('title', '新建生产工单')

@section('content')
<div class="card" style="max-width: 860px;">
    <div class="card-header">新建生产工单</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
        @endif
        <form method="POST" action="{{ route('production.orders.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">产品料号 <span class="text-danger">*</span></label>
                    <select name="product_material_id" class="form-select" required>
                        <option value="">请选择成品/半成品</option>
                        @foreach ($products as $p) <option value="{{ $p->id }}">{{ $p->material_code }} {{ $p->name }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">BOM 版本 <span class="text-danger">*</span></label>
                    <select name="bom_id" class="form-select" required>
                        <option value="">请选择已审核BOM</option>
                        @foreach ($boms as $b) <option value="{{ $b->id }}">{{ $b->bom_code }} v{{ $b->version }}</option> @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">计划数量 <span class="text-danger">*</span></label>
                    <input type="number" name="plan_qty" step="0.01" min="0.01" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">计划开始</label>
                    <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">计划完工</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
                <div class="col-md-12">
                    <label class="form-label">备注</label>
                    <input type="text" name="remark" class="form-control">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">保存工单</button>
                <a href="{{ route('production.orders') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
