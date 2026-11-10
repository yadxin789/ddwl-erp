@extends('layouts.admin')

@section('title', '新建客户')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header">新建客户</div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">客户编码 <span class="text-danger">*</span></label>
                    <input type="text" name="customer_code" class="form-control" value="{{ old('customer_code') }}" required>
                    @error('customer_code') <div class="text-danger small">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">客户名称 <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
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
                    <input type="text" name="payment_terms" class="form-control" value="{{ old('payment_terms') }}" placeholder="如 月结30天">
                </div>
                <div class="col-md-4">
                    <label class="form-label">信用额度（元）</label>
                    <input type="number" name="credit_limit" min="0" class="form-control" value="{{ old('credit_limit') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">税号</label>
                    <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number') }}">
                </div>
                <div class="col-12">
                    <label class="form-label">地址</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存</button>
                <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
