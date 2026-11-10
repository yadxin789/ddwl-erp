<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-05 研发 PLM 最小闭环：
 *  1) rnd_projects 增 stage（立项→设计→试产→量产）
 *  2) rnd_project_documents 增基线标记与版本替代关系
 *  3) 新增 ecr_change_orders（简版 ECR/ECO：变更单 + 关联 BOM + 审批 + 新版本 BOM）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->string('stage', 20)->default('initiation')->after('status')
                ->comment('阶段：initiation 立项 / design 设计 / trial 试产 / mass_production 量产');
            $table->timestamp('stage_changed_at')->nullable()->after('stage');
            $table->index('stage', 'idx_rnd_project_stage');
        });

        Schema::table('rnd_project_documents', function (Blueprint $table) {
            $table->boolean('is_baseline')->default(false)->after('audit_remark')
                ->comment('是否当前基线图档（同一项目只允许 1 个）');
            $table->timestamp('baseline_at')->nullable()->after('is_baseline');
            $table->unsignedBigInteger('superseded_by')->nullable()->after('baseline_at')
                ->comment('被哪一版基线替代（新文档 id）');
            $table->index('is_baseline', 'idx_rnd_doc_baseline');
        });

        Schema::create('ecr_change_orders', function (Blueprint $table) {
            $table->id();
            $table->string('change_no', 32)->unique()->comment('变更单号 ECR-YYYYMMDD-###');
            $table->unsignedBigInteger('project_id')->comment('所属研发项目');
            $table->unsignedBigInteger('bom_id')->comment('受影响 BOM 版本');
            $table->string('change_type', 20)->default('bom')
                ->comment('变更类型：bom 结构 / material 替代料 / process 工艺');
            $table->string('title', 128)->comment('变更标题');
            $table->text('reason')->comment('变更原因');
            $table->unsignedBigInteger('applicant_id');
            $table->string('status', 16)->default('pending')->comment('pending/approved/rejected');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('new_bom_id')->nullable()->comment('批准后生成的新 BOM 版本');
            $table->timestamps();
            $table->index('project_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecr_change_orders');
        Schema::table('rnd_project_documents', function (Blueprint $table) {
            $table->dropIndex('idx_rnd_doc_baseline');
            $table->dropColumn(['is_baseline', 'baseline_at', 'superseded_by']);
        });
        Schema::table('rnd_projects', function (Blueprint $table) {
            $table->dropIndex('idx_rnd_project_stage');
            $table->dropColumn(['stage', 'stage_changed_at']);
        });
    }
};
