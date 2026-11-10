@extends('layouts.admin')

@section('title', 'BOM Excel 导入')

@section('content')
<div class="card" style="max-width: 860px;">
    <div class="card-header">BOM Excel 导入</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger py-2">
                @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('bom.import.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">目标成品 <span class="text-danger">*</span></label>
                    <select name="product_material_id" class="form-select" required>
                        <option value="">请选择成品/半成品</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}">{{ $p->material_code }} {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Excel 文件 <span class="text-danger">*</span>（xls/xlsx/csv，≤20MB）</label>
                    <input type="file" name="file" class="form-control" accept=".xls,.xlsx,.csv" required>
                </div>

                <div class="col-12">
                    <div class="border rounded p-3 bg-light">
                        <div class="fw-semibold mb-2">列映射（首次导入需手动指定表头列名）</div>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label small mb-1">MPN列 <span class="text-danger">*</span></label>
                                <input type="text" name="mpn_column" class="form-control form-control-sm" value="{{ old('mpn_column', 'MPN') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">数量列 <span class="text-danger">*</span></label>
                                <input type="text" name="qty_column" class="form-control form-control-sm" value="{{ old('qty_column', '用量') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">物料名称列</label>
                                <input type="text" name="name_column" class="form-control form-control-sm" value="{{ old('name_column', '物料名称') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">规格列</label>
                                <input type="text" name="spec_column" class="form-control form-control-sm" value="{{ old('spec_column', '规格') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">位号列</label>
                                <input type="text" name="refdes_column" class="form-control form-control-sm" value="{{ old('refdes_column', '位号') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small mb-1">制造商列</label>
                                <input type="text" name="manufacturer_column" class="form-control form-control-sm" value="{{ old('manufacturer_column', '制造商') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i>上传并解析</button>
                <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
