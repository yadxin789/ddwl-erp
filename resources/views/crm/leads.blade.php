@extends('layouts.admin')

@section('title', '销售线索')

@section('content')
<div class="card">
    <div class="card-header">销售线索</div>
    <div class="card-body">
        <form method="POST" action="{{ route('crm.leads.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-3"><input name="company_name" class="form-control form-control-sm" placeholder="公司名称 *" required></div>
                <div class="col-md-2"><input name="contact_name" class="form-control form-control-sm" placeholder="联系人"></div>
                <div class="col-md-2"><input name="phone" class="form-control form-control-sm" placeholder="电话"></div>
                <div class="col-md-2"><input name="source" class="form-control form-control-sm" placeholder="来源"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">录入线索</button></div>
            </div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>线索号</th><th>公司</th><th>联系人</th><th>电话</th><th>来源</th><th>状态</th><th>负责人</th></tr></thead>
        <tbody>
            @forelse ($leads as $l)
            <tr>
                <td class="font-monospace">{{ $l->lead_no }}</td>
                <td>{{ $l->company_name }}</td>
                <td>{{ $l->contact_name ?? '-' }}</td>
                <td>{{ \App\Support\MaskingService::phone($l->phone) }}</td>
                <td>{{ $l->source ?? '-' }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\CrmLeadStatus::tryFrom($l->status)?->label() ?? $l->status }}</span></td>
                <td>{{ $l->owner->name ?? '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无线索</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $leads->links() }}</div>
</div>
@endsection
