<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每天凌晨 2 点检查研发任务逾期/今日到期
Schedule::command('rnd:check-deadlines')->dailyAt('02:00')->timezone('Asia/Shanghai');

// P2-05：每 10 分钟巡检队列/Outbox/同步失败，超阈值写 security_events
Schedule::command('erp:observe:scan --threshold=3')->everyTenMinutes()->timezone('Asia/Shanghai');

// P3-6：每月 1 日 01:00 自动生成上一个自然月的供应商绩效（同期重跑覆盖，命令幂等）
Schedule::command('erp:supplier:score')->monthlyOn(1, '01:00')->timezone('Asia/Shanghai');

// P2-02：每周一巡检旧角色/兼容角色回收进度（输出到日志）
Schedule::command('erp:legacy-role:audit')->weeklyOn(1, '08:00')->timezone('Asia/Shanghai');

// P2-02：到达 Q5 停用日（2026-12-31）后每天自动回收一次；命令幂等，无旧角色时为空操作
Schedule::command('erp:legacy-role:migrate --force')
    ->dailyAt('00:30')
    ->timezone('Asia/Shanghai')
    ->when(fn () => now()->toDateString() >= (string) config('navigation.legacy_role_deadline'));
