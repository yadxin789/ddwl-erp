@extends('layouts.admin')

@section('title', '经营驾驶舱')

@section('content')
<div class="alert alert-info small mb-3">
    <i class="fa-solid fa-lock me-1"></i>只读页面：仅汇总展示，不提供任何录入、审批、修改入口（老板岗不得操作业务单据）。
</div>

<div class="row g-3">
    @foreach ($cards as $card)
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100"><div class="card-body">
                <div class="text-muted small">{{ $card['label'] }}</div>
                <div class="fs-4 fw-bold">
                    @if (! empty($card['money']))¥{{ number_format((float) $card['value'], 2) }}@else{{ number_format((float) $card['value'], 0) }}@endif
                </div>
            </div></div>
        </div>
    @endforeach
</div>

@include('partials.dashboard-charts', ['charts' => $charts, 'cards' => $cards])

<div class="card mt-3"><div class="card-header">下钻报表</div><div class="card-body d-flex flex-wrap gap-2">
    @foreach ($readonlyReports as $report)
        <a href="{{ route($report['route']) }}" class="btn btn-sm btn-outline-secondary">{{ $report['name'] }}</a>
    @endforeach
</div></div>
@endsection
