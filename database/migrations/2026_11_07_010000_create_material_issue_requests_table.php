<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * v1.3 §4.3 / D-13：生产组长领料"按数量申请"，超 BOM 标准用量部分需生产主管审批并标"超耗"。
     * 独立成表，避免污染 material_issues（后者是仓管发料后的执行凭证）。
     */
    public function up(): void
    {
        Schema::create('material_issue_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 32)->unique()->comment('申请单号');
            $table->unsignedBigInteger('production_order_id')->comment('工单ID');
            $table->string('purpose', 200)->comment('用途');
            $table->string('status', 20)->default('approved')
                ->comment('pending_approval/approved/rejected/issued');
            $table->boolean('has_over_bom')->default(false)->comment('是否存在超 BOM 标准用量');
            $table->unsignedBigInteger('requested_by')->nullable()->comment('申请人');
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人（生产主管）');
            $table->timestamp('approved_at')->nullable()->comment('审批时间');
            $table->string('reject_reason', 200)->nullable()->comment('驳回原因');
            $table->timestamps();

            $table->index('production_order_id', 'idx_req_wo');
            $table->index('status', 'idx_req_status');
        });

        Schema::create('material_issue_request_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id')->comment('申请单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->decimal('qty', 12, 2)->comment('申请数量');
            $table->decimal('standard_qty', 12, 2)->nullable()->comment('按工单 BOM 计算的标准用量（留空=无 BOM 可比）');
            $table->boolean('is_over_bom')->default(false)->comment('本行是否超耗');
            $table->index('request_id', 'idx_req_line');
            $table->index('material_id', 'idx_req_material');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_issue_request_lines');
        Schema::dropIfExists('material_issue_requests');
    }
};
