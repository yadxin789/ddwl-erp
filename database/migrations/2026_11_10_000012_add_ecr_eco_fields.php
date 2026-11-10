<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-1 ECR/ECO 完整表单与影响分析：
 *  1) 变更单补优先级、计划生效日、变更范围、受影响物料、影响分析快照
 *  2) 批准后生成 ECO 号与执行时间（真正落新 BOM 版本）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecr_change_orders', function (Blueprint $table) {
            $table->string('priority', 10)->default('normal')->after('change_type')
                ->comment('优先级：low/normal/high');
            $table->date('effective_date')->nullable()->after('priority')
                ->comment('计划生效日（为空按批准日）');
            $table->string('scope_description', 500)->nullable()->after('title')
                ->comment('变更范围说明（工序/图档/物料）');
            $table->json('impacted_material_ids')->nullable()->after('scope_description')
                ->comment('受影响物料 id 列表');
            $table->json('impact_analysis')->nullable()->after('impacted_material_ids')
                ->comment('提交时的影响分析快照');
            $table->string('eco_no', 32)->nullable()->after('new_bom_id')
                ->comment('ECO 执行单号');
            $table->timestamp('eco_executed_at')->nullable()->after('eco_no');
        });
    }

    public function down(): void
    {
        Schema::table('ecr_change_orders', function (Blueprint $table) {
            $table->dropColumn(['priority', 'effective_date', 'scope_description', 'impacted_material_ids', 'impact_analysis', 'eco_no', 'eco_executed_at']);
        });
    }
};
