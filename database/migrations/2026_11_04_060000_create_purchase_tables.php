<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 7.3 采购订单表
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id()->comment('采购订单ID');
            $table->string('po_no', 32)->unique()->comment('采购订单号');
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->unsignedBigInteger('buyer_id')->comment('采购员ID');
            $table->date('order_date')->comment('订单日期');
            $table->date('expected_date')->nullable()->comment('预计到货日期');
            $table->string('status', 16)->default('draft')->comment('状态：draft/confirmed/received/closed/cancelled');
            $table->decimal('total_amount', 14, 2)->nullable()->comment('订单总金额');
            $table->decimal('received_qty', 12, 2)->default(0)->comment('已收货数量');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index('supplier_id', 'idx_supplier');
            $table->index('status', 'idx_status');
        });

        // 7.4 采购订单明细表
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('po_id')->comment('采购订单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->decimal('qty', 12, 2)->comment('订购数量');
            $table->decimal('received_qty', 12, 2)->default(0)->comment('已收货数量');
            $table->decimal('unit_price', 12, 4)->comment('单价');
            $table->string('remark', 255)->nullable()->comment('备注');

            $table->index('po_id', 'idx_po');
        });

        // 7.5 收货单表
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id()->comment('收货单ID');
            $table->string('receipt_no', 32)->unique()->comment('收货单号');
            $table->unsignedBigInteger('po_id')->comment('采购订单ID');
            $table->unsignedBigInteger('supplier_id')->comment('供应商ID');
            $table->date('receipt_date')->comment('收货日期');
            $table->unsignedBigInteger('warehouse_id')->comment('入库仓库ID');
            $table->string('status', 16)->default('pending_qc')->comment('状态：pending_qc/qc_passed/qc_failed/closed');
            $table->decimal('total_qty', 12, 2)->nullable()->comment('总数量');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index('po_id', 'idx_po');
        });

        // 7.6 收货明细表
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('receipt_id')->comment('收货单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->comment('批次号/Date Code');
            $table->decimal('qty', 12, 2)->comment('收货数量');
            $table->decimal('qualified_qty', 12, 2)->nullable()->comment('合格数量');
            $table->decimal('rejected_qty', 12, 2)->nullable()->comment('不合格数量');
            $table->string('supplier_batch', 64)->nullable()->comment('供应商批次号');

            $table->index('receipt_id', 'idx_receipt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
