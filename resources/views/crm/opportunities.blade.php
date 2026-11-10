@extends('layouts.admin')

@section('title', '商机')

@section('content')
<div class="card">
    <div class="card-header">销售商机</div>
    <div class="card-body">
        <form method="POST" action="{{ route('crm.opportunities.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="商机名称 *" required></div>
            <div class="col-md-3">
                <select name="lead_id" class="form-select form-select-sm">
                    <option value="">关联线索（可选）</option>
                    @foreach ($leads as $l)<option value="{{ $l->id }}">{{ $l->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="expected_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="预计金额 *" required></div>
            <div class="col-md-1"><input name="probability" type="number" min="0" max="100" class="form-control form-control-sm" placeholder="概率%" value="50"></div>
            <div class="col-md-2"><input name="expected_close_date" type="date" class="form-control form-control-sm"></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>商机号</th><th>名称</th><th>关联线索</th><th>预计金额</th><th>概率</th><th>预计成交日</th><th>阶段</th></tr></thead>
        <tbody>
            @forelse ($ops as $o)
            <tr>
                <td class="font-monospace">{{ $o->op_no }}</td>
                <td>{{ $o->name }}</td>
                <td>{{ $o->lead->company_name ?? '-' }}</td>
                <td>¥{{ number_format($o->expected_amount, 2) }}</td>
                <td>{{ $o->probability }}%</td>
                <td>{{ $o->expected_close_date }}</td>
                <td><span class="badge bg-info">{{ \App\Enums\CrmOpportunityStage::tryFrom($o->stage)?->label() ?? $o->stage }}</span></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无商机</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $ops->links() }}</div>
</div>
@endsection
