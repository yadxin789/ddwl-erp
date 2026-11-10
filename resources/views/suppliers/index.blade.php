@extends('layouts.admin')

@section('title', '供应商管理')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>供应商列表（共 {{ $suppliers->total() }} 条）</span>
        @can('supplier.create')
        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>新建供应商</a>
        @endcan
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="供应商编码/名称/联系人" value="{{ request('keyword') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100">查询</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>编码</th><th>供应商名称</th><th>类型</th><th>联系人</th><th>电话</th><th>交期(天)</th><th>状态</th><th>操作</th></tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                    <tr>
                        <td><span class="font-monospace">{{ $supplier->supplier_code }}</span></td>
                        <td>{{ $supplier->supplier_name }}</td>
                        <td>{{ $supplier->supplier_type ?? '-' }}</td>
                        <td>{{ $supplier->contact_person ?? '-' }}</td>
                        <td>{{ \App\Support\MaskingService::phone($supplier->contact_phone) }}</td>
                        <td>{{ $supplier->lead_time_days ?? '-' }}</td>
                        <td>@if ($supplier->status === 1) <span class="badge bg-success">启用</span> @else <span class="badge bg-secondary">停用</span> @endif</td>
                        <td>
                            @can('supplier.edit')
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">暂无供应商数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $suppliers->links() }}
    </div>
</div>
@endsection
