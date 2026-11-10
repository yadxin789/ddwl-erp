@extends('layouts.admin')

@section('title', '成本差异分析')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>成本差异分析（标准成本口径）</span>
        <a href="{{ route('finance.standard-costs') }}" class="btn btn-sm btn-outline-secondary">标准成本维护</a>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('finance.cost-variance') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-3"><label class="form-label small mb-0">开始</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><label class="form-label small mb-0">结束</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">查询</button></div>
        </form>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="text-muted small">采购价差合计（不摊入单笔销售成本）</div>
                    <div class="fs-5 {{ $varianceTotal >= 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format($varianceTotal, 2) }}</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3">
                    <div class="text-muted small">毛利（标准成本口径 = 出货收入 − 标准成本结转）</div>
                    <div class="fs-5">¥{{ number_format($grossTotal, 2) }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header">采购价差（按物料）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>物料</th><th>入库数量</th><th>价差</th></tr></thead>
        <tbody>
            @forelse ($varianceRows as $r)
                <tr>
                    <td>
                        @php($m = $materials[$r->material_id] ?? null)
                        {{ $m?->material_code ?? ('#'.$r->material_id) }} {{ $m?->name }}
                    </td>
                    <td>{{ number_format((float) $r->qty, 2) }}</td>
                    <td class="{{ (float) $r->variance >= 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format((float) $r->variance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-4">期间内没有带标准成本的入库流水（先维护标准成本，再做收货）</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card mb-3">
    <div class="card-header">采购价差（按供应商）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>供应商</th><th>价差</th></tr></thead>
        <tbody>
            @forelse ($supplierVariance as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td class="{{ (float) $s->variance >= 0 ? 'text-danger' : 'text-success' }}">¥{{ number_format((float) $s->variance, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center text-muted py-4">期间内没有可归集到供应商的价差</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card mb-3">
    <div class="card-header">量差（BOM 标准用量 vs 实际领料）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>物料</th><th>标准用量</th><th>实际领料</th><th>量差</th></tr></thead>
        <tbody>
            @forelse ($qtyVariance as $r)
                <tr>
                    <td>
                        @php($m = $qtyMaterials[$r->material_id] ?? null)
                        {{ $m?->material_code ?? ('#'.$r->material_id) }} {{ $m?->name }}
                    </td>
                    <td>{{ number_format($r->std_qty, 2) }}</td>
                    <td>{{ number_format($r->actual_qty, 2) }}</td>
                    <td class="{{ $r->diff_qty > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($r->diff_qty, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">期间内没有带 BOM 的生产工单</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-header">毛利（标准成本口径，按物料）</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>物料</th><th>出货数量</th><th>出货收入</th><th>标准成本结转</th><th>毛利</th></tr></thead>
        <tbody>
            @forelse ($gross as $g)
                <tr>
                    <td>{{ $g['material']?->material_code ?? '-' }} {{ $g['material']?->name }}</td>
                    <td>{{ number_format($g['qty'], 2) }}</td>
                    <td>¥{{ number_format($g['revenue'], 2) }}</td>
                    <td>¥{{ number_format($g['std_amount'], 2) }}</td>
                    <td class="{{ $g['gross_profit'] >= 0 ? 'text-success' : 'text-danger' }}">¥{{ number_format($g['gross_profit'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">期间内没有已结转标准成本的出货单</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
