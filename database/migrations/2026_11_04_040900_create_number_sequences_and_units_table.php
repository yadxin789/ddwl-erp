<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 系统扩展：number_sequences（编号规则表）、units（计量单位表）
     * 对齐 database-schema.md §20.1 / §20.2
     */
    public function up(): void
    {
        // 20.1 编号规则表
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id()->comment('规则ID');
            $table->string('rule_code', 32)->comment('规则编码：sales_order/purchase_order/work_order');
            $table->string('prefix', 16)->nullable()->comment('前缀：SO/PO/WO');
            $table->string('date_format', 16)->nullable()->comment('日期格式：Ymd');
            $table->integer('seq_length')->comment('流水号位数');
            $table->integer('current_seq')->default(0)->comment('当前流水号');
            $table->string('description', 255)->nullable()->comment('说明');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('rule_code', 'uk_rule_code');
        });

        // 20.2 计量单位表
        Schema::create('units', function (Blueprint $table) {
            $table->id()->comment('单位ID');
            $table->string('unit_code', 16)->comment('单位编码：PCS/K/M/卷/盘');
            $table->string('unit_name', 32)->comment('单位名称');
            $table->decimal('conversion_factor', 12, 4)->nullable()->comment('换算系数');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('unit_code', 'uk_unit_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
        Schema::dropIfExists('number_sequences');
    }
};
