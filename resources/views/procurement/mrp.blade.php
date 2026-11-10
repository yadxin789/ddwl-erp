@extends('layouts.admin')

@section('title', 'MRP 采购建议')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>MRP 采购建议（待处理 {{ $suggestions->count() }} 条）</span>
        <div class="d-flex gap-2">
            <a href="{{ route('proc.plans') }}" class="btn btn-sm btn-outline-secondary">采购计划</a>
            @can('procurement.operate')
            <form method="POST" action="{{ route('proc.mrp.run') }}">
                @csrf
                <button class="btn btn-sm btn-primary">重新计算</button>
            </form>
            @endcan
        </div>
    </div>
    <div class="card-body pb-0">
        <div class="alert alert-light border small py-2">
            口径：销售未交付 + <strong>未关联销售订单</strong>的独立工单需求 → 多层 BOM 展开（含损耗率）→ 减全公司可用库存（available）与采购在途（未收数量）→ 净需求。
            <br>关联销售订单的工单不再重复计入；重新计算只刷新"待处理"建议，已转申请保留。
        </div>
        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>物料</th><th>供应商</th><th>需求日</th>
                    <th class="text-end">毛需求</th><th class="text-end">可用</th><th class="text-end">在途</th>
                    <th class="text-end">净需求</th><th>需求来源</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($suggestions as $s)
                <tr>
                    <td>
                        <span class="font-monospace">{{ $s->material->material_code ?? '-' }}</span>
                        <div class="small text-muted">{{ $s->material->name ?? '' }}</div>
                    </td>
                    <td>{{ $s->supplier->supplier_name ?? '未指定' }}</td>
                    <td>{{ $s->required_date?->toDateString() ?? '-' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $s->gross_qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $s->available_qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $s->transit_qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="text-end fw-bold">{{ rtrim(rtrim(number_format((float) $s->net_qty, 2, '.', ''), '0'), '.') }}</td>
                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($s->demand_source, 48) }}</td>
                    <td>
                        @can('procurement.operate')
                        <form method="POST" action="{{ route('proc.mrp.convert', $s->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">转采购申请</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">暂无待处理建议，点"重新计算"生成</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($converted->isNotEmpty())
    <div class="card-footer">
        <div class="small text-muted mb-1">最近已转申请（保留审计）</div>
        <div class="d-flex flex-wrap gap-3 small">
            @foreach ($converted as $c)
                <span>{{ $c->material->material_code ?? '#' }}{{ $c->material_id }} × {{ $c->net_qty }} → 申请 #{{ $c->requisition_id }}</span>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
