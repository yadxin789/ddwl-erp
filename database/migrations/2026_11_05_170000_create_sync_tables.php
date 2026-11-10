<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('name', 128);
            $t->string('target', 64)->comment('taobao/jd/pdd/douyin/erp/wms');
            $t->string('direction', 16)->comment('pull/push');
            $t->string('data_type', 64)->comment('order/product/inventory');
            $t->string('cron_expr', 64)->nullable();
            $t->string('status', 16)->default('active')->comment('active/paused/error');
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
        });

        Schema::create('sync_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sync_task_id');
            $t->timestamp('run_at');
            $t->string('status', 16)->comment('success/failed/partial');
            $t->integer('total_count')->default(0);
            $t->integer('success_count')->default(0);
            $t->text('message')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('sync_tasks');
    }
};
