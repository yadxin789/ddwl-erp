@extends('layouts.admin')

@section('title', '领料申请')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="GET">
        <select name="status" class="form-select form-select-sm">
            <option value="">全部状态</option>
            @foreach (['pending_approval' => '待审批', 'approved' => '已批准', 'rejected' => '已驳回', 'issued' => '已发料'] as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">筛选</button>
    </form>
    @can('production.issue.request')
    <a href="{{ route('production.issue-requests.create') }}" class="btn btn-sm btn-primary">新建领料申请</a>
    @endcan
</div>

<div class="card">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>申请单号</th><th>工单</th><th>用途</th><th>明细</th><th>状态</th><th>申请人 / 审批人</th><th style="width:180px;">操作</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requests as $req)
                <tr>
                    <td class="font-monospace">{{ $req->request_no }}</td>
                    <td>{{ $req->productionOrder->order_no ?? '-' }}</td>
                    <td class="small">{{ $req->purpose }}</td>
                    <td class="small">
                        @foreach ($req->lines as $line)
                            <div>
                                {{ $line->material->material_code ?? '-' }} × {{ number_format((float) $line->qty, 2) }}
                                @if ($line->is_over_bom)
                                    <span class="badge bg-danger">超耗</span>
                                    <span class="text-muted">（BOM 标准 {{ number_format((float) $line->standard_qty, 2) }}）</span>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td>
                        @php
                            $map = ['pending_approval' => ['待审批', 'warning'], 'approved' => ['已批准', 'success'], 'rejected' => ['已驳回', 'danger'], 'issued' => ['已发料', 'secondary']];
                            [$label, $color] = $map[$req->status] ?? [$req->status, 'secondary'];
                        @endphp
                        <span class="badge bg-{{ $color }}">{{ $label }}</span>
                    </td>
                    <td class="small text-muted">
                        {{ $req->requester->identity_label ?? '-' }}
                        @if ($req->approver) / {{ $req->approver->identity_label }} @endif
                    </td>
                    <td>
                        @if ($req->status === 'pending_approval')
                            @can('production.issue.approve')
                            <form method="POST" action="{{ route('production.issue-requests.approve', $req) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success">批准</button>
                            </form>
                            <form method="POST" action="{{ route('production.issue-requests.reject', $req) }}" class="d-inline-flex gap-1 mt-1">
                                @csrf
                                <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="驳回原因" required>
                                <button class="btn btn-sm btn-outline-danger">驳回</button>
                            </form>
                            @endcan
                        @elseif ($req->status === 'approved')
                            @can('production.issue')
                            <form method="POST" action="{{ route('production.issue-requests.mark-issued', $req) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary">标记已发料</button>
                            </form>
                            @endcan
                        @else
                            <span class="text-muted small">{{ $req->reject_reason ?: '—' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">暂无领料申请</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $requests->links() }}</div>
</div>
@endsection
