@extends('layouts.admin')

@section('title', '编辑客户')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header">编辑客户：{{ $customer->customer_code }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">客户编码 <span class="text-danger">*</span></label>
                    <input type="text" name="customer_code" class="form-control" value="{{ $customer->customer_code }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">客户名称 <span class="text-danger">*</span></label>
                    <input type="text" name="customer_name" class="form-control" value="{{ $customer->customer_name }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">联系人</label>
                    <input type="text" name="contact_person" class="form-control" value="{{ $customer->contact_person }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">联系电话</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ $customer->contact_phone }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-control" value="{{ $customer->email }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">结算方式</label>
                    @include('partials.payment-terms-select', ['paymentTerms' => $paymentTerms, 'selected' => $customer->payment_terms])
                </div>
                <div class="col-md-4">
                    <label class="form-label">信用额度（元）</label>
                    <input type="number" name="credit_limit" min="0" class="form-control" value="{{ $customer->credit_limit }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">税号</label>
                    <input type="text" name="tax_number" class="form-control" value="{{ $customer->tax_number }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="1" @selected($customer->status === 1)>启用</option>
                        <option value="0" @selected($customer->status === 0)>停用</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">地址</label>
                    <input type="text" name="address" class="form-control" value="{{ $customer->address }}">
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
