<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 每天凌晨 2 点检查研发任务逾期/今日到期
Schedule::command('rnd:check-deadlines')->dailyAt('02:00')->timezone('Asia/Shanghai');
