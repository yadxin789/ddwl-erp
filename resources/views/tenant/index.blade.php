@extends('layouts.admin')

@section('title', '租户管理')

@section('content')
<div class="card">
    <div class="card-header">多租户管理</div>
    <div class="card-body">
        <form method="POST" action="{{ route('tenants.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="租户编码 T001" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="租户名称（公司）" required></div>
            <div class="col-md-2"><input name="contact_name" class="form-control form-control-sm" placeholder="联系人"></div>
            <div class="col-md-2"><input name="contact_phone" class="form-control form-control-sm" placeholder="联系电话"></div>
            <div class="col-md-2"><input type="date" name="expired_at" class="form-control form-control-sm" placeholder="到期日"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编码</th><th>名称</th><th>联系人</th><th>电话</th><th>到期日</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($tenants as $t)
            <tr><td class="font-monospace">{{ $t->code }}</td><td>{{ $t->name }}</td><td>{{ $t->contact_name ?? '-' }}</td><td>{{ $t->contact_phone ?? '-' }}</td><td>{{ $t->expired_at }}</td><td><span class="badge bg-secondary text-dark">{{ \App\Enums\TenantStatus::tryFrom($t->status)?->label() ?? $t->status }}</span></td></tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无租户</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $tenants->links() }}</div>
</div>
@endsection
