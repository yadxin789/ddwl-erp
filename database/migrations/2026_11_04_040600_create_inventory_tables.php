<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 仓储库存：inventory_stock（实时库存）、inventory_transactions（库存流水）、inventory_status_changes（库存状态转换记录）
     * 对齐 database-schema.sql §4.3 / §4.4 / §4.5
     * 库存流水表只增不改；库存状态：available/qc_hold/blocked/reserved/returned/repair/scrap
     */
    public function up(): void
    {
        // 4.3 实时库存表
        Schema::create('inventory_stock', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->nullable()->comment('批次号/Date Code');
            $table->unsignedBigInteger('warehouse_id')->comment('仓库ID');
            $table->unsignedBigInteger('location_id')->comment('库位ID');
            $table->string('status', 16)->default('available')->comment('库存状态：available/qc_hold/blocked/reserved/returned/repair/scrap');
            $table->decimal('qty', 12, 2)->default(0)->comment('当前数量');
            $table->decimal('reserved_qty', 12, 2)->default(0)->comment('已预留数量');
            $table->date('receive_date')->nullable()->comment('入库日期');
            $table->string('supplier_batch', 64)->nullable()->comment('供应商原始批次号');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['material_id', 'lot_no', 'warehouse_id', 'location_id', 'status'], 'uk_stock_dim');
            $table->index('material_id', 'idx_material');
            $table->index('status', 'idx_status');
            $table->index('warehouse_id', 'idx_warehouse_id');
            $table->index('location_id', 'idx_location_id');
        });

        // 4.4 库存流水表（只增不改）
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id()->comment('流水ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->nullable()->comment('批次号');
            $table->unsignedBigInteger('warehouse_id')->comment('仓库ID');
            $table->unsignedBigInteger('location_id')->comment('库位ID');
            $table->string('txn_type', 32)->comment('事务类型：receipt/issue/transfer/status_change/adjust/return_in/scrap_out');
            $table->decimal('qty_change', 12, 2)->comment('变动数量（正入负出）');
            $table->decimal('qty_before', 12, 2)->comment('变动前数量');
            $table->decimal('qty_after', 12, 2)->comment('变动后数量');
            $table->string('from_status', 16)->nullable()->comment('原状态');
            $table->string('to_status', 16)->nullable()->comment('新状态');
            $table->string('ref_type', 32)->nullable()->comment('来源单据类型');
            $table->unsignedBigInteger('ref_id')->nullable()->comment('来源单据ID');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('操作时间');

            $table->index(['material_id', 'lot_no'], 'idx_material_lot');
            $table->index(['ref_type', 'ref_id'], 'idx_ref');
            $table->index('created_at', 'idx_created');
        });

        // 4.5 库存状态转换记录表
        Schema::create('inventory_status_changes', function (Blueprint $table) {
            $table->id()->comment('记录ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->nullable()->comment('批次号');
            $table->string('from_status', 16)->comment('原状态');
            $table->string('to_status', 16)->comment('新状态');
            $table->decimal('qty', 12, 2)->comment('转换数量');
            $table->string('reason', 255)->nullable()->comment('转换原因');
            $table->string('approval_no', 32)->nullable()->comment('审批单号');
            $table->unsignedBigInteger('operator_id')->comment('操作人ID');
            $table->unsignedBigInteger('approver_id')->nullable()->comment('审批人ID');
            $table->timestamp('created_at')->nullable()->comment('转换时间');

            $table->index(['material_id', 'lot_no'], 'idx_material_lot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_status_changes');
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('inventory_stock');
    }
};
