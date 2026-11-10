<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 8)->unique()->comment('CNY/USD/EUR');
            $t->string('name', 32);
            $t->decimal('rate', 14, 6)->default(1)->comment('对人民币汇率');
            $t->boolean('is_base')->default(false);
            $t->timestamps();
        });

        // 客户发票（应收）
        Schema::create('customer_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_no', 32)->unique();
            $t->unsignedBigInteger('customer_id');
            $t->unsignedBigInteger('sales_order_id')->nullable();
            $t->date('invoice_date');
            $t->date('due_date')->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->decimal('tax_amount', 14, 2)->default(0);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('unpaid')->comment('unpaid/partial/paid/void');
            $t->timestamps();
        });

        // 供应商账单（应付）
        Schema::create('supplier_bills', function (Blueprint $t) {
            $t->id();
            $t->string('bill_no', 32)->unique();
            $t->unsignedBigInteger('supplier_id');
            $t->unsignedBigInteger('purchase_order_id')->nullable();
            $t->date('bill_date');
            $t->date('due_date')->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('unpaid');
            $t->timestamps();
        });

        // 收付款记录
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->string('payment_no', 32)->unique();
            $t->string('direction', 8)->comment('in/out');
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('method', 16)->comment('bank/cash/alipay/wechat');
            $t->date('paid_at');
            $t->string('ref_no', 64)->nullable()->comment('银行流水号');
            $t->unsignedBigInteger('user_id');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('supplier_bills');
        Schema::dropIfExists('customer_invoices');
        Schema::dropIfExists('currencies');
    }
};
