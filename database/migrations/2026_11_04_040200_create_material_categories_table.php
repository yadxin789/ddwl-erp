<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 物料主数据：material_categories（大类）、material_subcategories（中类）
     * 对齐 database-schema.sql §2.1 / §2.2
     */
    public function up(): void
    {
        // 2.1 物料大类表
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('category_code', 4)->comment('大类编码：EL/ST/PB/PA/WK/SM/FG/SP/OP/CH');
            $table->string('category_name', 64)->comment('大类名称');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->integer('sort_order')->default(0)->comment('排序号');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('category_code', 'uk_category_code');
        });

        // 2.2 物料中类表
        Schema::create('material_subcategories', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('category_code', 4)->comment('所属大类编码');
            $table->string('subcategory_code', 8)->comment('中类编码：EL-R/EL-C等');
            $table->string('subcategory_name', 64)->comment('中类名称');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('subcategory_code', 'uk_subcategory_code');
            $table->index('category_code', 'idx_category_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_subcategories');
        Schema::dropIfExists('material_categories');
    }
};
