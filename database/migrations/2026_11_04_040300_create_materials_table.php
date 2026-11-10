<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 物料主数据：materials（物料主表）、material_mpns（厂商型号关联表）
     * 对齐 database-schema.sql §2.3 / §2.4
     */
    public function up(): void
    {
        // 2.3 物料主表
        Schema::create('materials', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('material_code', 16)->comment('内部物料编码：EL000123');
            $table->string('category_code', 4)->comment('大类编码');
            $table->string('subcategory_code', 8)->nullable()->comment('中类编码');
            $table->string('name', 128)->comment('物料名称');
            $table->string('spec', 256)->comment('规格描述');
            $table->string('package', 32)->nullable()->comment('封装：0402/SOP-8/QFN-32');
            $table->string('unit', 8)->default('PCS')->comment('单位：PCS/K/M/卷/盘');
            $table->tinyInteger('rohs_status')->default(1)->comment('环保状态：1=RoHS 0=豁免 -1=禁用');
            $table->decimal('cost_price', 12, 4)->nullable()->comment('标准成本');
            $table->tinyInteger('status')->default(1)->comment('状态：1=active 0=inactive');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('material_code', 'uk_material_code');
            $table->index(['category_code', 'status'], 'idx_category_status');
        });

        // 2.4 厂商型号关联表
        Schema::create('material_mpns', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('material_id')->comment('关联物料ID');
            $table->string('mpn', 64)->comment('原厂型号');
            $table->string('manufacturer', 64)->comment('制造商：Yageo/Samsung/TI');
            $table->unsignedBigInteger('supplier_id')->nullable()->comment('主供供应商ID');
            $table->tinyInteger('is_primary')->default(0)->comment('是否主选MPN：1=是 0=否');
            $table->tinyInteger('is_alternative')->default(0)->comment('是否替代料：1=是 0=否');
            $table->string('approved_by', 64)->nullable()->comment('工程批准人');
            $table->timestamp('approved_at')->nullable()->comment('工程批准时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique(['mpn', 'manufacturer'], 'uk_mpn_mfr');
            $table->index('material_id', 'idx_material_id');
            $table->index('supplier_id', 'idx_supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_mpns');
        Schema::dropIfExists('materials');
    }
};
