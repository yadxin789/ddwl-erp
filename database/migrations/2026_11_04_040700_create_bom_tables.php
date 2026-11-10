<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 BOM管理：bom_headers（BOM主表）、bom_lines（BOM明细表）
     * 对齐 database-schema.sql §5.1 / §5.2
     */
    public function up(): void
    {
        // 5.1 BOM主表
        Schema::create('bom_headers', function (Blueprint $table) {
            $table->id()->comment('BOM ID');
            $table->string('bom_code', 32)->comment('BOM编号：BOM-FG000001-V1.0');
            $table->unsignedBigInteger('product_material_id')->comment('成品/半成品料号ID');
            $table->string('version', 16)->default('V1.0')->comment('版本号');
            $table->string('status', 16)->default('draft')->comment('状态：draft/approved/obsolete');
            $table->date('effective_date')->nullable()->comment('生效日期');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审核人');
            $table->timestamp('approved_at')->nullable()->comment('审核时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('bom_code', 'uk_bom_code');
            $table->index('product_material_id', 'idx_product');
            $table->index('status', 'idx_status');
        });

        // 5.2 BOM明细表
        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('bom_header_id')->comment('BOM主表ID');
            $table->unsignedBigInteger('parent_material_id')->nullable()->comment('父项料号ID（多层BOM展开用）');
            $table->unsignedBigInteger('material_id')->comment('子项内部料号ID');
            $table->decimal('qty_per', 12, 4)->comment('单位用量');
            $table->string('unit', 8)->default('PCS')->comment('单位');
            $table->string('ref_des', 256)->nullable()->comment('位号：R1,R2,R5');
            $table->decimal('loss_rate', 5, 2)->default(0)->comment('损耗率%');
            $table->tinyInteger('is_alternative')->default(0)->comment('是否替代料');
            $table->integer('sort_order')->default(0)->comment('排序号');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->index('bom_header_id', 'idx_bom');
            $table->index('material_id', 'idx_material');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('bom_headers');
    }
};
