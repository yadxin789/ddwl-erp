@extends('layouts.admin')

@section('title', '计量单位维护')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-1">计量单位维护</h1>
        <div class="small text-muted">归口部门：研发部。已被物料使用的单位只能停用，不能删除。</div>
    </div>
    <a href="{{ route('admin.master-data') }}" class="btn btn-sm btn-outline-secondary">返回归口台账</a>
</div>

<div class="card mb-3">
    <div class="card-header">新增计量单位</div>
    <form method="POST" action="{{ route('admin.master-data.units.store') }}" class="card-body">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">单位编码 <span class="text-danger">*</span></label>
                <input type="text" name="unit_code" class="form-control form-control-sm" required maxlength="16" placeholder="如 PCS">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">单位名称 <span class="text-danger">*</span></label>
                <input type="text" name="unit_name" class="form-control form-control-sm" required maxlength="32" placeholder="如 个">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">换算系数</label>
                <input type="number" step="0.0001" name="conversion_factor" class="form-control form-control-sm" placeholder="如 1000">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">状态</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary">添加</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">计量单位（{{ $units->count() }}）</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:140px;">编码</th>
                <th style="min-width:160px;">名称</th>
                <th style="width:150px;">换算系数</th>
                <th style="width:80px;">状态</th>
                <th style="width:110px;">物料引用</th>
                <th style="width:150px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($units as $unit)
                @php $used = (int) ($usage[$unit->unit_name] ?? $usage[$unit->unit_code] ?? 0); @endphp
                <tr class="{{ $unit->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $unit->unit_code }}</td>
                    <td>
                        <input type="text" name="unit_name" form="unitForm{{ $unit->id }}" value="{{ $unit->unit_name }}"
                               class="form-control form-control-sm" required maxlength="32">
                    </td>
                    <td>
                        <input type="number" step="0.0001" name="conversion_factor" form="unitForm{{ $unit->id }}"
                               value="{{ $unit->conversion_factor }}" class="form-control form-control-sm" min="0">
                        <input type="hidden" name="unit_code" form="unitForm{{ $unit->id }}" value="{{ $unit->unit_code }}">
                    </td>
                    <td>
                        <select name="status" form="unitForm{{ $unit->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($unit->status)>启用</option>
                            <option value="0" @selected(! $unit->status)>停用</option>
                        </select>
                    </td>
                    <td><span class="badge bg-info">{{ $used }}</span></td>
                    <td class="text-nowrap">
                        <form id="unitForm{{ $unit->id }}" method="POST" action="{{ route('admin.master-data.units.update', $unit) }}" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                        @if ($used === 0)
                            <form method="POST" action="{{ route('admin.master-data.units.destroy', $unit) }}" class="d-inline"
                                  onsubmit="return confirm('确定删除计量单位「{{ $unit->unit_name }}」吗？');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        @else
                            <span class="badge bg-warning text-dark">已引用</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
