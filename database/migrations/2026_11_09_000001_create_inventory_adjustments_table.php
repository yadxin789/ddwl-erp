<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-04：库存调整两段化单据（草稿→待审→生效 / 自动通过）
 * 金额口径见整改计划 §9.3：|差异数量| × 单位成本，≥ 阈值需仓库主管审批。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_no', 32)->unique();

            $table->string('source', 16)->default('manual');   // manual | stocktake
            $table->unsignedBigInteger('stock_id')->nullable();        // manual 调整的库存行
            $table->unsignedBigInteger('stocktake_id')->nullable();
            $table->unsignedBigInteger('stocktake_line_id')->nullable();

            $table->unsignedBigInteger('material_id');
            $table->string('lot_no', 64)->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();

            $table->string('status', 16)->default('pending');  // pending | approved | auto_approved | void
            $table->decimal('before_qty', 18, 4)->default(0);
            $table->decimal('after_qty', 18, 4)->default(0);
            $table->decimal('delta_qty', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('amount', 18, 2)->nullable();       // null = 无成本口径 → 强制审批
            $table->decimal('threshold', 18, 2)->nullable();
            $table->boolean('auto_approved')->default(false);

            $table->string('reason', 200);
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_remark', 200)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('stock_id');
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
