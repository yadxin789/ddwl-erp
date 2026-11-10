@extends('layouts.admin')

@section('title', '标准成本维护')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>标准成本维护（按版本，禁止覆盖历史）</span>
        <a href="{{ route('finance.cost-variance') }}" class="btn btn-sm btn-outline-secondary">成本差异分析</a>
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 small mb-3">
            当前 active 版本：<strong>{{ $demoCount }}</strong> 条演示价（source=demo，上线前由财务替换）、
            <strong>{{ $fallbackCount }}</strong> 条临时兜底（source=fallback，按最近采购价生成，请尽快定标）、
            未定标物料 <strong>{{ $missingCount }}</strong> 个。
            改价请新建版本；旧版本自动 superseded，已发货单据按发货时点版本结转，不会追溯。
        </div>

        <form method="GET" action="{{ route('finance.standard-costs') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-4"><input name="q" value="{{ $keyword }}" class="form-control form-control-sm" placeholder="物料编码或名称"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">搜索</button></div>
        </form>

        @if (session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger py-2">{{ $errors->first() }}</div>@endif
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
            <tr><th>物料</th><th>当前标准成本</th><th>版本 / 来源</th><th>生效日</th><th style="width:34%">新建版本</th></tr>
        </thead>
        <tbody>
            @forelse ($materials as $m)
                @php($v = $active[$m->id] ?? null)
                <tr>
                    <td>
                        <span class="font-monospace">{{ $m->material_code }}</span> {{ $m->name }}
                        <div class="text-muted small">{{ $m->spec }}</div>
                    </td>
                    <td>
                        @if ($v)
                            ¥{{ number_format($v->standard_cost, 4) }}
                            @if ($v->source === 'demo')<span class="badge bg-warning text-dark">演示数据</span>@endif
                            @if ($v->source === 'fallback')<span class="badge bg-warning text-dark">临时兜底</span>@endif
                        @else
                            <span class="text-muted">未定标</span>
                        @endif
                    </td>
                    <td>
                        @if ($v)
                            v{{ $v->version }} · {{ $v->label() }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $v?->effective_from?->toDateString() ?? '-' }}</td>
                    <td>
                        <form method="POST" action="{{ route('finance.standard-costs.store') }}" class="row g-1 align-items-center">
                            @csrf
                            <input type="hidden" name="material_id" value="{{ $m->id }}">
                            <div class="col-4"><input name="standard_cost" type="number" step="0.0001" min="0" class="form-control form-control-sm" placeholder="单价" required></div>
                            <div class="col-4"><input name="effective_from" type="date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                            <div class="col-4"><button class="btn btn-sm btn-primary w-100">生效</button></div>
                        </form>
                        @if ($v)
                            <form method="POST" action="{{ route('finance.standard-costs.deactivate') }}" class="mt-1">
                                @csrf
                                <input type="hidden" name="version_id" value="{{ $v->id }}">
                                <button class="btn btn-sm btn-outline-danger py-0">停用 v{{ $v->version }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">没有匹配的物料</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $materials->links() }}</div>
</div>
@endsection
