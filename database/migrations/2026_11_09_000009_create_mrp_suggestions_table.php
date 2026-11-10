<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-08 MRP 采购建议快照
 * - 每次"重新计算"只刷新 status=open 的行，converted/ignored 保留审计
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mrp_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('最近采购供应商，可空');
            $table->date('required_date')->nullable()->comment('最早需求日');
            $table->decimal('gross_qty', 14, 4)->default(0)->comment('毛需求（含损耗）');
            $table->decimal('available_qty', 14, 4)->default(0)->comment('现有可用');
            $table->decimal('transit_qty', 14, 4)->default(0)->comment('在途（采购未收）');
            $table->decimal('net_qty', 14, 2)->default(0)->comment('净需求（建议采购量）');
            $table->string('status', 16)->default('open')->comment('open/converted/ignored');
            $table->text('demand_source')->nullable()->comment('需求来源摘要');
            $table->unsignedBigInteger('requisition_id')->nullable()->comment('转成的采购申请');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'material_id'], 'idx_mrp_status_material');
            $table->index('required_date', 'idx_mrp_required_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mrp_suggestions');
    }
};
