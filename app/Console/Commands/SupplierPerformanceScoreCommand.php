<?php

namespace App\Console\Commands;

use App\Services\SupplierPerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * P3-6 供应商绩效自动计分（每月 1 日跑上一个月，可手工指定区间/月份）。
 *
 * 用法：
 *   php artisan erp:supplier:score                 # 上一个自然月
 *   php artisan erp:supplier:score --period=2026-11
 *   php artisan erp:supplier:score --start=2026-11-01 --end=2026-11-30
 */
class SupplierPerformanceScoreCommand extends Command
{
    protected $signature = 'erp:supplier:score
                            {--period= : 月份 YYYY-MM，默认上一个自然月}
                            {--start= : 自定义开始日期 YYYY-MM-DD}
                            {--end= : 自定义结束日期 YYYY-MM-DD}';

    protected $description = '按收货准时率与来料合格率生成供应商绩效快照（同期重跑覆盖）';

    public function handle(SupplierPerformanceService $service): int
    {
        try {
            if ($this->option('start') || $this->option('end')) {
                if (! $this->option('start') || ! $this->option('end')) {
                    $this->error('--start 与 --end 必须同时提供。');

                    return self::INVALID;
                }
                $start = Carbon::parse($this->option('start'))->startOfDay();
                $end = Carbon::parse($this->option('end'))->startOfDay();
                $label = $start->toDateString() . ' ~ ' . $end->toDateString();
            } else {
                $period = $this->option('period');
                if ($period && ! preg_match('/^\d{4}-\d{2}$/', $period)) {
                    $this->error('--period 格式应为 YYYY-MM。');

                    return self::INVALID;
                }
                $month = $period ? Carbon::parse($period . '-01') : now()->subMonthNoOverflow()->startOfMonth();
                $start = $month->copy()->startOfMonth();
                $end = $month->copy()->endOfMonth();
                $label = $start->format('Y-m');
            }

            $rows = $service->generate($start, $end);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("供应商绩效已生成 {$rows->count()} 家（{$label}，区间 {$start->toDateString()} ~ {$end->toDateString()}）。");
        if ($rows->isNotEmpty()) {
            $this->table(
                ['供应商', '收货单', '准时/逾期', '准时率', '合格率', '综合分', '等级'],
                $rows->map(fn ($r) => [
                    $r->supplier_code . ' ' . $r->supplier_name,
                    $r->receipt_count,
                    $r->on_time_count . '/' . $r->late_count,
                    $r->on_time_rate === null ? '-' : $r->on_time_rate . '%',
                    $r->quality_rate === null ? '-' : $r->quality_rate . '%',
                    $r->score === null ? '-' : $r->score,
                    $r->grade ?? '-',
                ])->all()
            );
        }

        return self::SUCCESS;
    }
}
