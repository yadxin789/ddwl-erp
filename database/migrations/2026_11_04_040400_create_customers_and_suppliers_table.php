<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 往来单位：customers（客户表）、suppliers（供应商表）
     * 对齐 database-schema.sql §3.1 / §3.2
     */
    public function up(): void
    {
        // 3.1 客户表
        Schema::create('customers', function (Blueprint $table) {
            $table->id()->comment('客户ID');
            $table->string('customer_code', 32)->comment('客户编码');
            $table->string('customer_name', 128)->comment('客户名称');
            $table->string('contact_person', 64)->nullable()->comment('联系人');
            $table->string('contact_phone', 32)->nullable()->comment('联系电话');
            $table->string('email', 128)->nullable()->comment('邮箱');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->string('tax_number', 64)->nullable()->comment('税号');
            $table->string('payment_terms', 64)->nullable()->comment('付款条件');
            $table->decimal('credit_limit', 12, 2)->nullable()->comment('信用额度');
            $table->tinyInteger('status')->default(1)->comment('状态：1=合作 0=停用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('customer_code', 'uk_customer_code');
        });

        // 3.2 供应商表
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id()->comment('供应商ID');
            $table->string('supplier_code', 32)->comment('供应商编码');
            $table->string('supplier_name', 128)->comment('供应商名称');
            $table->string('contact_person', 64)->nullable()->comment('联系人');
            $table->string('contact_phone', 32)->nullable()->comment('联系电话');
            $table->string('email', 128)->nullable()->comment('邮箱');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->string('supplier_type', 32)->nullable()->comment('类型：原厂/代理/贸易/加工');
            $table->string('payment_terms', 64)->nullable()->comment('付款条件');
            $table->integer('lead_time_days')->nullable()->comment('标准交期（天）');
            $table->tinyInteger('status')->default(1)->comment('状态：1=合作 0=停用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('supplier_code', 'uk_supplier_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
    }
};
