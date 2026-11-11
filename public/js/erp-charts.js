/* ==========================================================================
   DDWL-ERP 图表公共封装（依赖 public/js/echarts.min.js）
   统一配色、字体、图例、提示框与环形图标签样式，页面只传数据。
   ========================================================================== */
(function () {
    'use strict';

    var palette = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
    var fontFamily = '"Microsoft YaHei", "PingFang SC", "Segoe UI", Arial, sans-serif';

    function baseTextStyle() {
        return {
            fontFamily: fontFamily,
            color: '#334155'
        };
    }

    function tooltipStyle() {
        return {
            backgroundColor: 'rgba(15, 23, 42, .92)',
            borderWidth: 0,
            textStyle: { color: '#fff', fontSize: 12, fontFamily: fontFamily },
            extraCssText: 'box-shadow: 0 8px 24px rgba(15, 23, 42, .18); border-radius: 8px;'
        };
    }

    function init(id) {
        var el = document.getElementById(id);
        if (!el || !window.echarts) {
            return null;
        }

        var chart = window.echarts.init(el, null, { renderer: 'canvas' });
        window.addEventListener('resize', function () {
            chart.resize();
        });

        return chart;
    }

    function lineOption(months, series) {
        return {
            color: palette,
            textStyle: baseTextStyle(),
            tooltip: Object.assign({ trigger: 'axis', axisPointer: { type: 'shadow' } }, tooltipStyle()),
            legend: {
                bottom: 0,
                itemWidth: 10,
                itemHeight: 10,
                itemGap: 18,
                textStyle: { fontSize: 11, color: '#64748b', fontFamily: fontFamily }
            },
            grid: {
                left: 8,
                right: 12,
                top: 18,
                bottom: 38,
                containLabel: true
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: months,
                axisLine: { lineStyle: { color: '#e2e8f0' } },
                axisTick: { show: false },
                axisLabel: { color: '#64748b', fontSize: 11 }
            },
            yAxis: {
                type: 'value',
                minInterval: 1,
                axisLabel: { color: '#64748b', fontSize: 11 },
                splitLine: { lineStyle: { color: '#f1f5f9' } }
            },
            series: (series || []).map(function (item, index) {
                return {
                    name: item.name,
                    type: 'line',
                    smooth: true,
                    symbol: 'circle',
                    symbolSize: 5,
                    showSymbol: false,
                    lineStyle: { width: 2 },
                    areaStyle: { opacity: index === 0 ? 0.16 : 0.06 },
                    emphasis: { focus: 'series' },
                    data: item.data
                };
            })
        };
    }

    function donutOption(labels, values) {
        return {
            color: palette,
            textStyle: baseTextStyle(),
            tooltip: Object.assign({ trigger: 'item' }, tooltipStyle()),
            legend: {
                bottom: 0,
                itemWidth: 10,
                itemHeight: 10,
                itemGap: 14,
                textStyle: { fontSize: 11, color: '#64748b', fontFamily: fontFamily }
            },
            series: [{
                name: '本岗指标',
                type: 'pie',
                radius: ['46%', '72%'],
                center: ['50%', '44%'],
                avoidLabelOverlap: true,
                itemStyle: {
                    borderRadius: 10,
                    borderColor: '#fff',
                    borderWidth: 2
                },
                label: {
                    show: true,
                    formatter: '{b}\n{c}',
                    position: 'outside',
                    distance: 8,
                    fontSize: 11,
                    lineHeight: 15,
                    color: '#475569'
                },
                labelLine: {
                    show: true,
                    length: 12,
                    length2: 8,
                    lineStyle: { color: '#cbd5e1' }
                },
                data: (labels || []).map(function (label, index) {
                    return { name: label, value: values[index] || 0 };
                })
            }]
        };
    }

    window.ErpCharts = {
        init: init,
        lineOption: lineOption,
        donutOption: donutOption,
        palette: palette
    };
})();
