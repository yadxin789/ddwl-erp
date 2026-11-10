@extends('layouts.admin')

@section('title', '供应商绩效')

@section('content')
<div class="alert alert-light border small py-2 mb-2">数据范围：{{ $scopeLabel }}</div>
<div class="row g-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">供应商数</div><div class="fs-4 fw-bold">{{ number_format($supplierCount) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">收货单数</div><div class="fs-4 fw-bold">{{ number_format($receipts) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
        <div class="text-muted small">累计收货数量</div><div class="fs-4 fw-bold">{{ number_format($receiptQty, 0) }}</div>
    </div></div></div>
</div>

<div class="card mt-3"><div class="card-header">供应商应付 TOP10（按账单额）</div>
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>供应商</th><th class="text-end">账单额</th></tr></thead>
        <tbody>
        @forelse ($topSuppliers as $row)
            <tr><td>{{ $row->supplier_name }}</td><td class="text-end">¥{{ number_format((float) $row->billed, 2) }}</td></tr>
        @empty
            <tr><td colspan="2" class="text-center text-muted py-3">暂无供应商账单</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>供应商绩效自动计分</span>
        <span class="small text-muted">准时率 40% + 来料合格率 60%</span>
    </div>
    <div class="card-body">
        <div class="alert alert-light border small py-2">
            口径：准时率 = 有采购交期且 <code>收货日期 ≤ 交期</code> 的收货单 / 有交期的收货单；来料合格率 = 合格数 /（合格数 + 不合格数），未检验明细不计入分母；某维度无样本时按剩余权重归一化，都无样本显示“样本不足”。等级 A≥90、B≥80、C≥70、D&lt;70。
        </div>
        <form method="POST" action="{{ route('report.supplier.score') }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-auto">
                <label class="form-label small mb-1">开始日期</label>
                <input type="date" name="start" class="form-control form-control-sm" value="{{ $scoreStart->toDateString() }}" required>
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">结束日期</label>
                <input type="date" name="end" class="form-control form-control-sm" value="{{ $scoreEnd->toDateString() }}" required>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">生成本期绩效</button>
            </div>
            <div class="col-auto small text-muted">已生成 {{ $scores->count() }} 家；重复生成会覆盖同期快照。</div>
        </form>
        @if ($errors->any())
            <div class="text-danger small mb-2">{{ $errors->first() }}</div>
        @endif

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>供应商</th>
                    <th class="text-end">收货单</th>
                    <th class="text-end">准时 / 逾期</th>
                    <th class="text-end">准时率</th>
                    <th class="text-end">来料合格率</th>
                    <th class="text-end">综合分</th>
                    <th>等级</th>
                    <th>生成时间</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($scores as $row)
                    <tr>
                        <td>{{ $row->supplier_code }} · {{ $row->supplier_name }}</td>
                        <td class="text-end">{{ $row->receipt_count }}</td>
                        <td class="text-end">{{ $row->on_time_count }} / {{ $row->late_count }}</td>
                        <td class="text-end">{{ $row->on_time_rate === null ? '无交期样本' : number_format((float) $row->on_time_rate, 2) . '%' }}</td>
                        <td class="text-end">{{ $row->quality_rate === null ? '无检验样本' : number_format((float) $row->quality_rate, 2) . '%' }}</td>
                        <td class="text-end fw-semibold">{{ $row->score === null ? '样本不足' : number_format((float) $row->score, 2) }}</td>
                        <td>
                            @if ($row->grade)
                                <span class="badge {{ $row->grade === 'A' ? 'bg-success' : ($row->grade === 'B' ? 'bg-primary' : ($row->grade === 'C' ? 'bg-warning text-dark' : 'bg-danger')) }}">{{ $row->grade }}</span>
                            @else
                                <span class="text-muted small">-</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $row->updated_at?->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">本期还没有快照，点“生成本期绩效”计算。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
