<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-01：审批引擎支持"代批留痕 + 业务上下文 + 完成时间"。
 * - payload_json：业务动作上下文（如 purchase_order.confirm 需要知道是"确认"还是"关闭"）
 * - delegated：本实例的最终决策是否由代批人做出（D-21 月度占比统计用）
 * - approved_at：通过时间（审批时长与月度统计用）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_instances', function (Blueprint $table) {
            $table->json('payload_json')->nullable()->after('logs_json')->comment('业务动作上下文');
            $table->boolean('delegated')->default(false)->after('payload_json')->comment('是否代批决策');
            $table->timestamp('approved_at')->nullable()->after('delegated');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('status', 16)->default('pending')->after('method')
                ->comment('pending/approved/executed/rejected');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('approval_instances', function (Blueprint $table) {
            $table->dropColumn(['payload_json', 'delegated', 'approved_at']);
        });
    }
};
