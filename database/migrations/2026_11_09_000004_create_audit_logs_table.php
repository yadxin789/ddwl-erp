<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-01：通用审计流水表（审批通过/驳回/代批先落这里，后续 P2 的敏感操作也复用）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人');
            $table->string('action', 64)->comment('approval.approve / approval.reject / approval.pass 等');
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('detail')->nullable()->comment('JSON 明细');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['target_type', 'target_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
