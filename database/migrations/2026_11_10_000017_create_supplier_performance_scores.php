<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_performance_scores', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('生成时供应商所属租户；平台账号为空');
            $table->string('supplier_code', 32);
            $table->string('supplier_name', 128);
            $table->unsignedInteger('receipt_count')->default(0)->comment('收货单数');
            $table->unsignedInteger('on_time_count')->default(0)->comment('有交期且准时收货单数');
            $table->unsignedInteger('late_count')->default(0)->comment('逾期收货单数');
            $table->decimal('qualified_qty', 14, 2)->default(0);
            $table->decimal('rejected_qty', 14, 2)->default(0);
            $table->decimal('on_time_rate', 5, 2)->nullable()->comment('准时率 %');
            $table->decimal('quality_rate', 5, 2)->nullable()->comment('来料合格率 %');
            $table->decimal('score', 5, 2)->nullable()->comment('综合得分');
            $table->string('grade', 4)->nullable()->comment('A/B/C/D');
            $table->json('detail_json')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end', 'supplier_id'], 'uk_supplier_period');
            $table->index('supplier_id', 'idx_sps_supplier');
            $table->index('tenant_id', 'idx_sps_tenant');
            $table->index(['period_start', 'period_end'], 'idx_sps_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_performance_scores');
    }
};
