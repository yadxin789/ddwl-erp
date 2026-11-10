<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-03 财务核销闭环（D-04）
 * - customer_invoices + shipment_id（发票可按发货单/销售订单生成）
 * - payment_allocations：收款核销发票、付款核销供应商账单（金额可拆分）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('shipment_id')->nullable()->after('sales_order_id')->comment('关联发货单（按发货开票时写）');
            $table->index('shipment_id', 'idx_invoice_shipment');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('invoice_id')->nullable()->comment('收款核销的客户发票');
            $table->unsignedBigInteger('bill_id')->nullable()->comment('付款核销的供应商账单');
            $table->decimal('amount', 14, 2)->comment('本次核销金额');
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->index('payment_id', 'idx_alloc_payment');
            $table->index('invoice_id', 'idx_alloc_invoice');
            $table->index('bill_id', 'idx_alloc_bill');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoice_shipment');
            $table->dropColumn('shipment_id');
        });
    }
};
