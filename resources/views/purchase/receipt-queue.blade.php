@extends('layouts.admin')

@section('title', $queue === 'putaway' ? '收货上架' : '送检队列')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            @if ($queue === 'putaway')
                待上架收货单（质检合格 {{ $receipts->total() }} 条）
            @else
                待送检收货单（待质检 {{ $receipts->total() }} 条）
            @endif
        </span>
        <div class="d-flex gap-2">
            <a href="{{ route('purchase.receipts.inspect-queue') }}" class="btn btn-sm btn-outline-warning">待送检</a>
            <a href="{{ route('purchase.receipts.pending') }}" class="btn btn-sm btn-outline-success">待上架</a>
            <a href="{{ route('purchase.receipts') }}" class="btn btn-sm btn-outline-secondary">全部收货单</a>
        </div>
    </div>
    <div class="card-body pb-0">
        <p class="text-muted small mb-3">
            @if ($queue === 'putaway')
                质检合格后库存已转入 available，此页用于核对数量与库位并归档收货单。
            @else
                收货登记后库存处于 qc_hold，需由品质岗（IQC）判定；收货岗不参与判定。
            @endif
        </p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>收货单号</th><th>采购单</th><th>供应商</th><th>收货日期</th><th>仓库</th><th>数量</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @php $st = ['pending_qc'=>'待质检','qc_passed'=>'质检合格','qc_failed'=>'质检不合格','closed'=>'已关闭']; @endphp
                @forelse ($receipts as $r)
                <tr>
                    <td class="font-monospace">{{ $r->receipt_no }}</td>
                    <td class="font-monospace">{{ $r->purchaseOrder->po_no ?? '-' }}</td>
                    <td>{{ $r->supplier->supplier_name ?? '-' }}</td>
                    <td>{{ $r->receipt_date }}</td>
                    <td>{{ $r->warehouse->name ?? '-' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($r->total_qty, 2), '0'), '.') }}</td>
                    <td><span class="badge bg-{{ $r->status === 'pending_qc' ? 'warning' : 'success' }} text-dark">{{ $st[$r->status] ?? $r->status }}</span></td>
                    <td>
                        <a href="{{ route('purchase.receipts.show', $r) }}" class="btn btn-sm btn-outline-primary">
                            {{ $queue === 'putaway' ? '核对明细' : '查看' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">
                    {{ $queue === 'putaway' ? '没有待上架的收货单' : '没有待质检的收货单' }}
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $receipts->links() }}</div>
</div>
@endsection
