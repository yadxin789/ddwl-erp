@php
    $chartMonths = $charts['months'] ?? [];
    $chartSeries = $charts['series'] ?? [];
    $hasTrend = ! empty($chartSeries);
    $countCards = collect($cards)
        ->reject(fn ($card) => ! empty($card['money']))
        ->filter(fn ($card) => is_numeric($card['value']))
        ->values();
    $hasMix = $countCards->count() >= 2;
@endphp

@if ($hasTrend || $hasMix)
<div class="row g-3 mt-1">
    @if ($hasTrend)
    <div class="{{ $hasMix ? 'col-lg-7' : 'col-12' }}">
        <div class="card chart-card h-100">
            <div class="card-header">
                <span><i class="fa-solid fa-chart-line me-2 text-primary"></i>近 6 个月业务量趋势</span>
                <small>按本岗可见数据</small>
            </div>
            <div class="card-body pt-2">
                <div id="trendChart" class="chart-box"></div>
            </div>
        </div>
    </div>
    @endif

    @if ($hasMix)
    <div class="{{ $hasTrend ? 'col-lg-5' : 'col-12' }}">
        <div class="card chart-card h-100">
            <div class="card-header">
                <span><i class="fa-solid fa-chart-pie me-2 text-success"></i>本岗指标构成</span>
                <small>单位：数量</small>
            </div>
            <div class="card-body pt-2">
                <div id="mixChart" class="chart-box"></div>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

@push('scripts')
<script src="{{ asset('js/echarts.min.js') }}"></script>
<script src="{{ asset('js/erp-charts.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if ($hasTrend)
    var trendChart = ErpCharts.init('trendChart');
    if (trendChart) {
        trendChart.setOption(ErpCharts.lineOption(
            @json($chartMonths, JSON_UNESCAPED_UNICODE),
            @json($chartSeries, JSON_UNESCAPED_UNICODE)
        ));
    }
    @endif

    @if ($hasMix)
    var mixChart = ErpCharts.init('mixChart');
    if (mixChart) {
        mixChart.setOption(ErpCharts.donutOption(
            @json($countCards->pluck('label')->all(), JSON_UNESCAPED_UNICODE),
            @json($countCards->map(fn ($card) => (float) $card['value'])->all())
        ));
    }
    @endif
});
</script>
@endpush
