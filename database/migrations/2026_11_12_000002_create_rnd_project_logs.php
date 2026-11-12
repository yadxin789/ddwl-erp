<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 研发项目履历表：记录项目全生命周期的关键动作，供"项目履历"页展示。
 * 与通用表 project_logs（无操作人字段）区分，本表自带 user_id 与任务关联。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rnd_project_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人');
            $table->string('action', 64)->comment('动作：创建项目/派发任务/上传交付物/审核通过…');
            $table->string('detail', 500)->nullable()->comment('动作明细');
            $table->string('log_type', 32)->default('operation')
                ->comment('operation 常规 / status 状态 / stage 阶段 / document 交付物 / extension 延期');
            $table->timestamps();
            $table->index('project_id');
            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnd_project_logs');
    }
};
