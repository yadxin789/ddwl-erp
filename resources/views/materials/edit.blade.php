@extends('layouts.admin')

@section('title', '编辑物料')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header">编辑物料：<span class="font-monospace">{{ $material->material_code }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('materials.update', $material) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">物料大类 <span class="text-danger">*</span></label>
                    <select name="category_code" class="form-select" required>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->category_code }}" @selected($material->category_code === $cat->category_code)>
                                {{ $cat->category_code }} {{ $cat->category_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">物料中类</label>
                    <input type="text" name="subcategory_code" class="form-control" value="{{ $material->subcategory_code }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">物料名称 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $material->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">规格型号 <span class="text-danger">*</span></label>
                    <input type="text" name="spec" class="form-control" value="{{ $material->spec }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">封装</label>
                    <input type="text" name="package" class="form-control" value="{{ $material->package }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">单位 <span class="text-danger">*</span></label>
                    <input type="text" name="unit" class="form-control" value="{{ $material->unit }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">成本价（元）</label>
                    <input type="number" name="cost_price" step="0.0001" min="0" class="form-control" value="{{ $material->cost_price }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ROHS状态</label>
                    <select name="rohs_status" class="form-select">
                        <option value="1" @selected($material->rohs_status === 1)>ROHS</option>
                        <option value="0" @selected($material->rohs_status === 0)>非ROHS</option>
                        <option value="-1" @selected($material->rohs_status === -1)>未认证</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="1" @selected($material->status === 1)>启用</option>
                        <option value="0" @selected($material->status === 0)>停用</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">备注</label>
                    <textarea name="remark" class="form-control" rows="2">{{ $material->remark }}</textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存</button>
                <a href="{{ route('materials.index') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
