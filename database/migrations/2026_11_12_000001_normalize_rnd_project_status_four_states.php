<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 项目状态收敛为四态：
 *   立项中(initiation) / 进行中(in_progress) / 已暂停(paused) / 已完成(completed)
 *
 * 取消 overdue（逾期进行中）与 overdue_completed（逾期完成）：
 * 项目是否逾期由「进度 + 计划日期」在页面上直接判断，不再落成一个独立状态。
 * 任务状态不受影响，仍保留 overdue。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 历史数据归并：逾期类状态回落到四态
        DB::table('rnd_projects')->where('status', 'overdue')->update(['status' => 'in_progress']);
        DB::table('rnd_projects')->where('status', 'overdue_completed')->update(['status' => 'completed']);
        // 兼容可能存在的 not_started 写法
        DB::table('rnd_projects')->where('status', 'not_started')->update(['status' => 'initiation']);

        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->string('status', 20)->default('initiation')
                ->comment('initiation 立项中 / in_progress 进行中 / paused 已暂停 / completed 已完成')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->string('status', 20)->default('in_progress')
                ->comment('in_progress/overdue/completed/overdue_completed/paused')
                ->change();
        });
    }
};
