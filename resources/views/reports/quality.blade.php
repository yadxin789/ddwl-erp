@extends('layouts.admin')

@section('title', '质量报表')

@section('content')
<div class="alert alert-light border small py-2 mb-2">数据范围：{{ $scopeLabel }}</div>
<div class="row g-3">
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small">检验单数</div><div class="fs-4 fw-bold">{{ number_format($total) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small">抽检总数</div><div class="fs-4 fw-bold">{{ number_format($sample) }}</div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small">合格数 / 不良数</div>
        <div class="fs-5 fw-bold"><span class="text-success">{{ number_format($qualified) }}</span> / <span class="text-danger">{{ number_format($defective) }}</span></div>
    </div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body">
        <div class="text-muted small">合格率</div>
        <div class="fs-4 fw-bold">{{ $passRate === null ? '—' : $passRate . '%' }}</div>
    </div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6"><div class="card"><div class="card-header">按检验环节</div>
        <table class="table table-sm mb-0"><thead class="table-light"><tr><th>环节</th><th class="text-end">单数</th><th class="text-end">合格</th><th class="text-end">不良</th></tr></thead>
            <tbody>
            @forelse ($byStage as $row)
                <tr><td>{{ \App\Support\DisplayText::stage($row->stage) }}</td><td class="text-end">{{ $row->cnt }}</td><td class="text-end">{{ number_format((float) $row->ok) }}</td><td class="text-end text-danger">{{ number_format((float) $row->ng) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">暂无检验记录</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">不良备注 TOP10（原始记录汇总）</div>
        <table class="table table-sm mb-0"><thead class="table-light"><tr><th>备注</th><th class="text-end">出现次数</th></tr></thead>
            <tbody>
            @forelse ($topDefects as $row)
                <tr><td class="small">{{ $row->remark }}</td><td class="text-end">{{ $row->cnt }}</td></tr>
            @empty
                <tr><td colspan="2" class="text-center text-muted py-3">暂无</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="card-footer small text-muted">缺陷字典现有 {{ $defectCodes->count() }} 条，可在品质管理维护。</div>
    </div></div>
</div>
@endsection
