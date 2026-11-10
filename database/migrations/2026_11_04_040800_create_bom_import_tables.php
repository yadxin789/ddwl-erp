<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 BOM管理：bom_imports（导入任务表）、bom_import_rows（导入临时行表）、bom_column_templates（列映射模板表）
     * 对齐 database-schema.sql §5.3 / §5.4 / §5.5
     */
    public function up(): void
    {
        // 5.3 BOM导入任务表
        Schema::create('bom_imports', function (Blueprint $table) {
            $table->id()->comment('导入ID');
            $table->string('import_no', 32)->comment('导入编号');
            $table->string('file_name', 255)->comment('原始文件名');
            $table->string('file_path', 512)->comment('文件存储路径');
            $table->unsignedBigInteger('product_material_id')->comment('目标成品料号ID');
            $table->integer('total_rows')->default(0)->comment('总行数');
            $table->integer('success_rows')->default(0)->comment('成功行数');
            $table->integer('error_rows')->default(0)->comment('待新料号审批行数');
            $table->string('status', 16)->default('uploaded')->comment('状态：uploaded/parsed/confirmed/imported');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('import_no', 'uk_import_no');
        });

        // 5.4 BOM导入临时行表
        Schema::create('bom_import_rows', function (Blueprint $table) {
            $table->id()->comment('行ID');
            $table->unsignedBigInteger('import_id')->comment('导入任务ID');
            $table->integer('row_no')->comment('Excel行号');
            $table->string('raw_mpn', 128)->nullable()->comment('原始MPN');
            $table->string('raw_name', 256)->nullable()->comment('原始物料名称');
            $table->string('raw_spec', 256)->nullable()->comment('原始规格');
            $table->string('raw_qty', 32)->nullable()->comment('原始数量');
            $table->string('raw_ref_des', 256)->nullable()->comment('原位号');
            $table->string('raw_manufacturer', 64)->nullable()->comment('原始制造商');
            $table->unsignedBigInteger('matched_material_id')->nullable()->comment('匹配到的内部料号ID');
            $table->string('match_status', 16)->default('unmatched')->comment('匹配状态：matched/multiple/unmatched/manual');
            $table->string('action', 16)->default('import')->comment('处理动作：import/new_material');
            $table->unsignedBigInteger('new_material_request_id')->nullable()->comment('新料号申请单ID');
            $table->string('error_message', 255)->nullable()->comment('错误信息');

            $table->index('import_id', 'idx_import');
        });

        // 5.5 BOM列映射模板表
        Schema::create('bom_column_templates', function (Blueprint $table) {
            $table->id()->comment('模板ID');
            $table->string('template_name', 64)->comment('模板名称');
            $table->string('mpn_column', 32)->nullable()->comment('MPN列名');
            $table->string('qty_column', 32)->nullable()->comment('数量列名');
            $table->string('spec_column', 32)->nullable()->comment('规格列名');
            $table->string('refdes_column', 32)->nullable()->comment('位号列名');
            $table->string('manufacturer_column', 32)->nullable()->comment('制造商列名');
            $table->string('name_column', 32)->nullable()->comment('物料名称列名');
            $table->tinyInteger('is_default')->default(0)->comment('是否默认模板');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_column_templates');
        Schema::dropIfExists('bom_import_rows');
        Schema::dropIfExists('bom_imports');
    }
};
