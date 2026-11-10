@extends('layouts.admin')

@section('title', '新建供应商')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header">新建供应商</div>
    <div class="card-body">
        <form method="POST" action="{{ route('suppliers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">供应商编码 <span class="text-danger">*</span></label>
                    <input type="text" name="supplier_code" class="form-control" value="{{ old('supplier_code') }}" required>
                    @error('supplier_code') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">供应商名称 <span class="text-danger">*</span></label>
                    <input type="text" name="supplier_name" class="form-control" value="{{ old('supplier_name') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">供应商类型</label>
                    <input type="text" name="supplier_type" class="form-control" value="{{ old('supplier_type') }}" placeholder="原厂/代理/加工">
                </div>
                <div class="col-md-4">
                    <label class="form-label">联系人</label>
                    <input type="text" name="contact_person" class="form-control" value="{{ old('contact_person') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">联系电话</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">结算方式</label>
                    <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">采购交期（天）</label>
                    <input type="number" name="lead_time_days" min="0" class="form-control" value="{{ old('lead_time_days') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">地址</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存</button>
                <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
