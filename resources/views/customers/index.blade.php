@extends('layouts.admin')

@section('title', '客户管理')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>客户列表（共 {{ $customers->total() }} 条）</span>
        @can('customer.create')
        <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>新建客户</a>
        @endcan
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="客户编码/名称/联系人" value="{{ request('keyword') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100">查询</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>客户编码</th><th>客户名称</th><th>联系人</th><th>电话</th><th>结算方式</th><th>信用额度</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                    <tr>
                        <td><span class="font-monospace">{{ $customer->customer_code }}</span></td>
                        <td>{{ $customer->customer_name }}</td>
                        <td>{{ $customer->contact_person ?? '-' }}</td>
                        <td>{{ \App\Support\MaskingService::phone($customer->contact_phone) }}</td>
                        <td>{{ $customer->payment_terms ?? '-' }}</td>
                        <td>{{ $customer->credit_limit ? number_format($customer->credit_limit) : '-' }}</td>
                        <td>@if ($customer->status === 1) <span class="badge bg-success">启用</span> @else <span class="badge bg-secondary">停用</span> @endif</td>
                        <td>
                            @can('customer.edit')
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">暂无客户数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
    </div>
</div>
@endsection
