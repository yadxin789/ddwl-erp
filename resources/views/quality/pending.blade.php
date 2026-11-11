@extends('layouts.admin')

@section('title', 'IQC 待检台')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>IQC 待检台（qc_hold 批次 {{ $batches->count() }} 条）</span>
        <div class="d-flex gap-2 align-items-center">
            @if ($locked && $stage)
                <span class="badge bg-primary">本岗位：{{ $stage }}（阶段锁定）</span>
            @else
                <span class="badge bg-secondary">品质主管视角（全阶段）</span>
            @endif
            <a href="{{ route('quality.inspections') }}" class="btn btn-sm btn-outline-secondary">检验记录</a>
            @can('quality.operate')
            <a href="{{ route('quality.inspections.create') }}" class="btn btn-sm btn-primary">录入检验</a>
            @endcan
        </div>
    </div>
    <div class="card-body pb-0">
        <div class="alert alert-light border small py-2">
            待检口径 = 库存状态 <code>qc_hold</code> 的收货批次；判定入口在收货单详情（IQC 判定后合格转 available、不合格转 blocked）。
            本页只读汇总，按物料 / 供应商 / 到货日排序。
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>物料</th><th>批次</th><th>供应商</th><th>收货单</th><th>到货日</th>
                    <th class="text-end">待检数量</th><th>仓库</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $b)
                <tr>
                    <td>
                        <span class="font-monospace">{{ $b->material->material_code ?? '-' }}</span>
                        <div class="small text-muted">{{ $b->material->name ?? '' }}</div>
                    </td>
                    <td class="font-monospace">{{ $b->lot_no !== '' ? $b->lot_no : '-' }}</td>
                    <td>{{ $b->supplier->supplier_name ?? '-' }}</td>
                    <td>
                        @if ($b->receipt)
                            <a href="{{ route('purchase.receipts.show', $b->receipt) }}" class="font-monospace">{{ $b->receipt->receipt_no }}</a>
                            <span class="badge bg-{{ $b->receipt->status === 'pending_qc' ? 'warning text-dark' : 'success' }}">{{ \App\Support\DisplayText::status($b->receipt->status) }}</span>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $b->receipt_date }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($b->qty, 2, '.', ''), '0'), '.') }}</td>
                    <td>{{ $b->stock->warehouse->warehouse_name ?? ('#' . $b->warehouse_id) }}</td>
                    <td>
                        @if ($b->receipt)
                            <a href="{{ route('purchase.receipts.show', $b->receipt) }}" class="btn btn-sm btn-outline-primary">判定</a>
                        @else
                            <span class="small text-muted">无关联收货单</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">当前没有待检批次</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
