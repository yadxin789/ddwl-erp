<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_reconciles', function (Blueprint $t) {
            $t->id();
            $t->string('reconcile_no', 32)->unique();
            $t->date('period');
            $t->string('type', 16)->comment('bank/customer/supplier');
            $t->decimal('book_amount', 14, 2)->default(0);
            $t->decimal('actual_amount', 14, 2)->default(0);
            $t->decimal('diff_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('draft');
            $t->timestamps();
        });

        Schema::create('finance_settles', function (Blueprint $t) {
            $t->id();
            $t->string('settle_no', 32)->unique();
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('draft');
            $t->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $t) {
            $t->id();
            $t->string('tx_no', 32)->unique();
            $t->string('category', 64)->comment('收入/支出/转账/成本');
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('direction', 8)->comment('debit/credit');
            $t->string('summary', 256)->nullable();
            $t->date('tx_date');
            $t->timestamps();
        });

        Schema::create('expense_allocations', function (Blueprint $t) {
            $t->id();
            $t->string('alloc_no', 32)->unique();
            $t->string('dept_code', 32)->nullable();
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('basis', 64)->nullable()->comment('按人数/按面积/按产值');
            $t->date('period');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_allocations');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('finance_settles');
        Schema::dropIfExists('finance_reconciles');
    }
};
