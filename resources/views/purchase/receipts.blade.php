@extends('layouts.admin')

@section('title', '收货单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>收货单（共 {{ $receipts->total() }} 条）</span>
        <form class="d-flex gap-1" method="GET">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="收货单号">
            <button class="btn btn-sm btn-outline-secondary">搜索</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>收货单号</th><th>采购单</th><th>供应商</th><th>收货日期</th><th>仓库</th><th>数量</th><th>状态</th><th>操作</th></tr>
            </thead>
            <tbody>
                @forelse ($receipts as $r)
                <tr>
                    <td class="font-monospace">{{ $r->receipt_no }}</td>
                    <td class="font-monospace">{{ $r->purchaseOrder->po_no ?? '-' }}</td>
                    <td>{{ $r->supplier->supplier_name ?? '-' }}</td>
                    <td>{{ $r->receipt_date }}</td>
                    <td>{{ $r->warehouse->name ?? '-' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($r->total_qty,2),'0'),'.') }}</td>
                    <td>
                        @php $m = ['pending_qc'=>'待质检','qc_passed'=>'质检合格','qc_failed'=>'质检不合格','closed'=>'已关闭']; @endphp
                        <span class="badge bg-{{ $r->status==='pending_qc' ? 'warning' : ($r->status==='qc_passed' ? 'success' : 'danger') }} text-dark">{{ $m[$r->status] ?? $r->status }}</span>
                    </td>
                    <td><a href="{{ route('purchase.receipts.show', $r) }}" class="btn btn-sm btn-outline-primary">详情/质检</a></td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">暂无收货单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $receipts->links() }}</div>
</div>
@endsection
