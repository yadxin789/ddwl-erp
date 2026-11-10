<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-2 APS 有限产能排程：
 *  - aps_plans：一次排程的编号/区间/汇总
 *  - aps_plan_entries：按"工单 + 工序 + 日期"拆分的日负荷（每个工作中心每天不超过 capacity_per_day）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aps_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_no', 32)->unique();
            $table->date('horizon_start');
            $table->date('horizon_end');
            $table->string('status', 16)->default('draft')->comment('draft/published');
            $table->json('summary_json')->nullable()->comment('排程汇总（工单/工序/工时/溢出/利用率）');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('aps_plan_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('production_order_id');
            $table->unsignedInteger('operation_seq');
            $table->string('operation_name', 64);
            $table->unsignedBigInteger('work_center_id');
            $table->date('work_date');
            $table->decimal('hours', 8, 2);
            $table->timestamps();
            $table->index(['plan_id', 'work_center_id', 'work_date'], 'idx_aps_load');
            $table->index(['plan_id', 'production_order_id'], 'idx_aps_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aps_plan_entries');
        Schema::dropIfExists('aps_plans');
    }
};
