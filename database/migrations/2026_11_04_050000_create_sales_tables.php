<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-1 销售管理模块（7张表）
 * 对齐 database-schema.sql §6.1 ~ §6.7
 */
return new class extends Migration
{
    public function up(): void
    {
        // 6.1 报价单
        Schema::create('sales_quotes', function (Blueprint $table) {
            $table->id()->comment('报价单ID');
            $table->string('quote_no', 32)->comment('报价单号');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->date('quote_date')->comment('报价日期');
            $table->date('valid_until')->nullable()->comment('有效期至');
            $table->string('status', 16)->default('draft')->comment('状态：draft/confirmed/converted/expired');
            $table->decimal('total_amount', 14, 2)->nullable()->comment('总金额');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique('quote_no', 'uk_quote_no');
            $table->index('customer_id', 'idx_customer');
        });

        // 6.2 报价明细
        Schema::create('sales_quote_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('quote_id')->comment('报价单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->decimal('qty', 12, 2)->comment('数量');
            $table->decimal('unit_price', 12, 4)->comment('单价');
            $table->integer('delivery_days')->nullable()->comment('交期（天）');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->index('quote_id', 'idx_quote');
        });

        // 6.3 销售订单
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id()->comment('订单ID');
            $table->string('order_no', 32)->comment('销售订单号');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->unsignedBigInteger('quote_id')->nullable()->comment('来源报价单ID');
            $table->date('order_date')->comment('订单日期');
            $table->date('delivery_date')->nullable()->comment('要求交货日期');
            $table->string('status', 16)->default('draft')->comment('状态：draft/confirmed/picking/shipped/closed/cancelled');
            $table->decimal('total_amount', 14, 2)->nullable()->comment('订单总金额');
            $table->decimal('shipped_qty', 12, 2)->default(0)->comment('已发货数量');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->unsignedBigInteger('salesperson_id')->nullable()->comment('销售员ID');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique('order_no', 'uk_order_no');
            $table->index('customer_id', 'idx_customer');
        });

        // 6.4 销售订单明细
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('order_id')->comment('销售订单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->decimal('qty', 12, 2)->comment('订单数量');
            $table->decimal('shipped_qty', 12, 2)->default(0)->comment('已发货数量');
            $table->decimal('unit_price', 12, 4)->comment('单价');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->index('order_id', 'idx_order');
        });

        // 6.5 出货单
        Schema::create('shipments', function (Blueprint $table) {
            $table->id()->comment('出货单ID');
            $table->string('shipment_no', 32)->comment('出货单号');
            $table->unsignedBigInteger('sales_order_id')->comment('销售订单ID');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->date('shipment_date')->comment('出货日期');
            $table->string('logistics_company', 64)->nullable()->comment('物流公司');
            $table->string('tracking_no', 64)->nullable()->comment('物流单号');
            $table->string('status', 16)->default('picked')->comment('状态：picked/shipped/delivered');
            $table->decimal('total_qty', 12, 2)->nullable()->comment('总数量');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique('shipment_no', 'uk_shipment_no');
            $table->index('sales_order_id', 'idx_order');
        });

        // 6.6 客户退货单
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id()->comment('退货单ID');
            $table->string('return_no', 32)->comment('退货单号');
            $table->unsignedBigInteger('sales_order_id')->nullable()->comment('原销售订单ID');
            $table->unsignedBigInteger('customer_id')->comment('客户ID');
            $table->string('return_reason', 255)->nullable()->comment('退货原因');
            $table->string('status', 16)->default('pending')->comment('状态：pending/received/inspecting/closed');
            $table->decimal('total_qty', 12, 2)->default(0)->comment('退货总数');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            $table->unique('return_no', 'uk_return_no');
            $table->index('customer_id', 'idx_customer');
        });

        // 6.7 退货明细
        Schema::create('customer_return_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('return_id')->comment('退货单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->nullable()->comment('退回批次号');
            $table->decimal('qty', 12, 2)->comment('退回数量');
            $table->string('disposition', 20)->nullable()->comment('判定结果：back_to_stock/repair/scrap/return_to_supplier');
            $table->decimal('disposition_qty', 12, 2)->nullable()->comment('分流后数量');
            $table->timestamp('handled_at')->nullable()->comment('判定时间');
            $table->index('return_id', 'idx_return');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_lines');
        Schema::dropIfExists('customer_returns');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_quote_lines');
        Schema::dropIfExists('sales_quotes');
    }
};
