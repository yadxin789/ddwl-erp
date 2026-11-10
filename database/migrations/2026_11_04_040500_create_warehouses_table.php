<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0 仓储库存：warehouses（仓库表）、warehouse_locations（库位表）
     * 对齐 database-schema.sql §4.1 / §4.2
     */
    public function up(): void
    {
        // 4.1 仓库表
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id()->comment('仓库ID');
            $table->string('warehouse_code', 16)->comment('仓库编码');
            $table->string('name', 64)->comment('仓库名称');
            $table->string('type', 20)->comment('类型：raw_material/semi_finished/finished_goods/return/scrap/repair/tooling');
            $table->string('address', 255)->nullable()->comment('仓库地址');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('warehouse_code', 'uk_warehouse_code');
        });

        // 4.2 库位表
        Schema::create('warehouse_locations', function (Blueprint $table) {
            $table->id()->comment('库位ID');
            $table->unsignedBigInteger('warehouse_id')->comment('所属仓库ID');
            $table->string('location_code', 32)->comment('库位编码：A-01-03');
            $table->string('zone', 32)->nullable()->comment('区域：待检区/合格区/冻结区/报废区/维修区/线边');
            $table->tinyInteger('status')->default(1)->comment('状态：1=启用 0=禁用');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            $table->unique('location_code', 'uk_location_code');
            $table->index('warehouse_id', 'idx_warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_locations');
        Schema::dropIfExists('warehouses');
    }
};
