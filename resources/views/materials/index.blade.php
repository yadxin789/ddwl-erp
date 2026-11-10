@extends('layouts.admin')

@section('title', '物料管理')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>物料列表（共 {{ $materials->total() }} 条）</span>
        @can('material.create')
        <a href="{{ route('materials.create') }}" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i>新建物料
        </a>
        @endcan
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="编码/名称/规格" value="{{ request('keyword') }}">
            </div>
            <div class="col-md-3">
                <select name="category_code" class="form-select form-select-sm">
                    <option value="">全部大类</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->category_code }}" @selected(request('category_code') === $cat->category_code)>
                            {{ $cat->category_code }} {{ $cat->category_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100"><i class="fa-solid fa-magnifying-glass me-1"></i>查询</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>物料编码</th><th>名称</th><th>规格</th><th>封装</th><th>单位</th>
                        <th>大类</th><th>成本价</th><th>ROHS</th><th>状态</th><th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($materials as $material)
                    <tr>
                        <td><span class="font-monospace">{{ $material->material_code }}</span></td>
                        <td>{{ $material->name }}</td>
                        <td class="small">{{ $material->spec }}</td>
                        <td>{{ $material->package ?? '-' }}</td>
                        <td>{{ $material->unit }}</td>
                        <td><span class="badge bg-secondary">{{ $material->category_code }}</span></td>
                        <td>{{ $material->cost_price ? number_format($material->cost_price, 4) : '-' }}</td>
                        <td>
                            @if ($material->rohs_status === 1) <span class="badge bg-success">ROHS</span>
                            @elseif ($material->rohs_status === 0) <span class="badge bg-warning text-dark">非ROHS</span>
                            @else <span class="badge bg-light text-muted">未认证</span> @endif
                        </td>
                        <td>
                            @if ($material->status === 1) <span class="badge bg-success">启用</span>
                            @else <span class="badge bg-secondary">停用</span> @endif
                        </td>
                        <td>
                            @can('material.edit')
                            <a href="{{ route('materials.edit', $material) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">暂无物料数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $materials->links() }}
    </div>
</div>
@endsection
