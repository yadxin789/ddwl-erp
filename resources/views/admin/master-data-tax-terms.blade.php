@extends('layouts.admin')

@section('title', '税率与结算方式')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-1">税率与结算方式</h1>
        <div class="small text-muted">归口部门：财务部。税率按税目维护；结算方式供客户/供应商账期口径统一。</div>
    </div>
    <a href="{{ route('admin.master-data') }}" class="btn btn-sm btn-outline-secondary">返回归口台账</a>
</div>

<div class="card mb-3">
    <div class="card-header">税目</div>
    <form method="POST" action="{{ route('admin.master-data.tax-entities.store') }}" class="card-body">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">税目编码 <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control form-control-sm" required maxlength="32" placeholder="如 VAT_GENERAL">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">税目名称 <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="128" placeholder="如 增值税（一般纳税人）">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">类型</label>
                <input type="text" name="type" class="form-control form-control-sm" maxlength="32" placeholder="如 vat">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">状态</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">添加税目</button></div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:140px;">编码</th>
                <th style="min-width:180px;">名称</th>
                <th style="width:110px;">类型</th>
                <th style="width:80px;">状态</th>
                <th style="width:90px;">税率数</th>
                <th style="width:150px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($entities as $entity)
                <tr class="{{ $entity->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $entity->code }}</td>
                    <td>
                        <input type="text" name="name" form="entityForm{{ $entity->id }}" value="{{ $entity->name }}"
                               class="form-control form-control-sm" required maxlength="128">
                        <input type="hidden" name="code" form="entityForm{{ $entity->id }}" value="{{ $entity->code }}">
                    </td>
                    <td>
                        <input type="text" name="type" form="entityForm{{ $entity->id }}" value="{{ $entity->type }}"
                               class="form-control form-control-sm" maxlength="32">
                    </td>
                    <td>
                        <select name="status" form="entityForm{{ $entity->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($entity->status)>启用</option>
                            <option value="0" @selected(! $entity->status)>停用</option>
                        </select>
                    </td>
                    <td><span class="badge bg-info">{{ $entity->rates->count() }}</span></td>
                    <td class="text-nowrap">
                        <form id="entityForm{{ $entity->id }}" method="POST" action="{{ route('admin.master-data.tax-entities.update', $entity) }}" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                        @if ($entity->rates->isEmpty())
                            <form method="POST" action="{{ route('admin.master-data.tax-entities.destroy', $entity) }}" class="d-inline"
                                  onsubmit="return confirm('确定删除税目「{{ $entity->name }}」吗？');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        @else
                            <span class="badge bg-warning text-dark">有税率</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">税率</div>
    <form method="POST" action="{{ route('admin.master-data.tax-rates.store') }}" class="card-body">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">税目 <span class="text-danger">*</span></label>
                <select name="tax_entity_id" class="form-select form-select-sm" required>
                    @foreach ($entities as $entity)
                        <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">税率名称 <span class="text-danger">*</span></label>
                <input type="text" name="tax_type" class="form-control form-control-sm" required maxlength="32" placeholder="如 标准税率">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">税率 % <span class="text-danger">*</span></label>
                <input type="number" step="0.001" name="rate" class="form-control form-control-sm" required min="0" max="100" placeholder="13.000">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">状态</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">添加税率</button></div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="min-width:200px;">税目</th>
                <th style="min-width:150px;">税率名称</th>
                <th style="width:110px;">税率%</th>
                <th style="width:80px;">状态</th>
                <th style="width:150px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rates as $rate)
                <tr class="{{ $rate->status ? '' : 'table-secondary' }}">
                    <td>
                        <select name="tax_entity_id" form="rateForm{{ $rate->id }}" class="form-select form-select-sm" required>
                            @foreach ($entities as $entity)
                                <option value="{{ $entity->id }}" @selected($rate->tax_entity_id === $entity->id)>{{ $entity->name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <input type="text" name="tax_type" form="rateForm{{ $rate->id }}" value="{{ $rate->tax_type }}"
                               class="form-control form-control-sm" required maxlength="32">
                    </td>
                    <td>
                        <input type="number" step="0.001" name="rate" form="rateForm{{ $rate->id }}" value="{{ $rate->rate }}"
                               class="form-control form-control-sm" required min="0" max="100">
                    </td>
                    <td>
                        <select name="status" form="rateForm{{ $rate->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($rate->status)>启用</option>
                            <option value="0" @selected(! $rate->status)>停用</option>
                        </select>
                    </td>
                    <td class="text-nowrap">
                        <form id="rateForm{{ $rate->id }}" method="POST" action="{{ route('admin.master-data.tax-rates.update', $rate) }}" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                        <form method="POST" action="{{ route('admin.master-data.tax-rates.destroy', $rate) }}" class="d-inline"
                              onsubmit="return confirm('确定删除这条税率吗？');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">删除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">结算方式</div>
    <form method="POST" action="{{ route('admin.master-data.payment-terms.store') }}" class="card-body">
        @csrf
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">编码 <span class="text-danger">*</span></label>
                <input type="text" name="code" class="form-control form-control-sm" required maxlength="32" placeholder="如 NET30">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">名称 <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control form-control-sm" required maxlength="64" placeholder="如 月结30天">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">账期天数</label>
                <input type="number" name="days" class="form-control form-control-sm" min="0" max="365" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">说明</label>
                <input type="text" name="description" class="form-control form-control-sm" maxlength="255">
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">状态</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">添加结算方式</button></div>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:120px;">编码</th>
                <th style="min-width:150px;">名称</th>
                <th style="width:100px;">账期天数</th>
                <th>说明</th>
                <th style="width:80px;">状态</th>
                <th style="width:150px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($terms as $term)
                <tr class="{{ $term->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $term->code }}</td>
                    <td>
                        <input type="text" name="name" form="termForm{{ $term->id }}" value="{{ $term->name }}"
                               class="form-control form-control-sm" required maxlength="64">
                        <input type="hidden" name="code" form="termForm{{ $term->id }}" value="{{ $term->code }}">
                    </td>
                    <td>
                        <input type="number" name="days" form="termForm{{ $term->id }}" value="{{ $term->days }}"
                               class="form-control form-control-sm" min="0" max="365" required>
                    </td>
                    <td>
                        <input type="text" name="description" form="termForm{{ $term->id }}" value="{{ $term->description }}"
                               class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <select name="status" form="termForm{{ $term->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($term->status)>启用</option>
                            <option value="0" @selected(! $term->status)>停用</option>
                        </select>
                    </td>
                    <td class="text-nowrap">
                        <form id="termForm{{ $term->id }}" method="POST" action="{{ route('admin.master-data.payment-terms.update', $term) }}" class="d-inline">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                        <form method="POST" action="{{ route('admin.master-data.payment-terms.destroy', $term) }}" class="d-inline"
                              onsubmit="return confirm('确定删除结算方式「{{ $term->name }}」吗？');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">删除</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
