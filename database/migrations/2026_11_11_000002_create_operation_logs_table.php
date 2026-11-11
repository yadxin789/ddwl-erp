<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 统一操作流水：记录“谁”对“哪张业务表的哪条记录”做了“什么”，
 * 以及变更前后的字段值。用于普通 CRUD 的个人追溯。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id()->comment('日志ID');
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID（平台账号为空）');
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人用户ID');
            $table->string('actor_name', 64)->nullable()->comment('操作人姓名快照');
            $table->string('action', 16)->comment('动作：created/updated/deleted');
            $table->string('target_type', 64)->comment('对象表名');
            $table->string('target_id', 64)->nullable()->comment('对象记录ID');
            $table->string('target_label', 255)->nullable()->comment('对象可读标识（单号/名称）');
            $table->json('changes')->nullable()->comment('变更内容：before/after');
            $table->string('ip_address', 45)->nullable()->comment('操作IP');
            $table->string('user_agent', 255)->nullable()->comment('客户端UA');
            $table->timestamp('created_at')->nullable()->comment('操作时间');

            $table->index(['tenant_id', 'created_at'], 'idx_oplog_tenant_time');
            $table->index(['user_id', 'created_at'], 'idx_oplog_user_time');
            $table->index(['target_type', 'target_id'], 'idx_oplog_target');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
};
