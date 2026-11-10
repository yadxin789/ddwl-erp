<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 8.1 工作中心
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id()->comment('工作中心ID');
            $table->string('code', 16)->unique()->comment('工作中心编码');
            $table->string('name', 64)->comment('工作中心名称');
            $table->string('location', 64)->nullable()->comment('所在位置');
            $table->decimal('capacity_per_day', 8, 2)->nullable()->comment('日产能');
            $table->boolean('status')->default(true)->comment('状态：1=运行 0=停用');
            $table->timestamps();
        });

        // 8.2 工艺路线
        Schema::create('routings', function (Blueprint $table) {
            $table->id()->comment('工艺路线ID');
            $table->string('routing_code', 32)->unique()->comment('工艺路线编码');
            $table->unsignedBigInteger('product_material_id')->comment('产品料号ID');
            $table->string('version', 16)->default('v1.0')->comment('版本号');
            $table->string('status', 16)->default('draft')->comment('状态：draft/approved/obsolete');
            $table->timestamps();
            $table->index('product_material_id', 'idx_product');
        });

        // 8.3 工序列表
        Schema::create('routing_operations', function (Blueprint $table) {
            $table->id()->comment('工序ID');
            $table->unsignedBigInteger('routing_id')->comment('工艺路线ID');
            $table->integer('operation_seq')->comment('工序顺序');
            $table->string('operation_name', 64)->comment('工序名称');
            $table->unsignedBigInteger('work_center_id')->comment('工作中心ID');
            $table->decimal('standard_hours', 8, 2)->nullable()->comment('标准工时');
            $table->string('description', 255)->nullable()->comment('工序说明');
            $table->index('routing_id', 'idx_routing');
        });

        // 8.4 生产工单
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id()->comment('工单ID');
            $table->string('order_no', 32)->unique()->comment('工单号');
            $table->unsignedBigInteger('product_material_id')->comment('生产产品料号ID');
            $table->unsignedBigInteger('bom_id')->comment('使用的BOM版本ID');
            $table->unsignedBigInteger('routing_id')->nullable()->comment('工艺路线ID');
            $table->decimal('plan_qty', 12, 2)->comment('计划数量');
            $table->decimal('completed_qty', 12, 2)->default(0)->comment('完工数量');
            $table->decimal('defective_qty', 12, 2)->default(0)->comment('不良数量');
            $table->string('status', 16)->default('planned')->comment('状态：planned/released/in_progress/completed/closed/cancelled');
            $table->date('start_date')->nullable()->comment('计划开始日期');
            $table->date('end_date')->nullable()->comment('计划完工日期');
            $table->unsignedBigInteger('sales_order_id')->nullable()->comment('关联销售订单ID');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->index('status', 'idx_status');
        });

        // 8.5 工序报工记录
        Schema::create('production_operation_logs', function (Blueprint $table) {
            $table->id()->comment('报工ID');
            $table->unsignedBigInteger('production_order_id')->comment('生产工单ID');
            $table->string('operation_name', 64)->comment('工序名称');
            $table->unsignedBigInteger('worker_id')->comment('报工人ID');
            $table->decimal('good_qty', 12, 2)->comment('良品数量');
            $table->decimal('defective_qty', 12, 2)->default(0)->comment('不良数量');
            $table->string('defect_code', 32)->nullable()->comment('不良代码');
            $table->decimal('work_hours', 6, 2)->nullable()->comment('工时');
            $table->timestamp('reported_at')->comment('报工时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->index('production_order_id', 'idx_wo');
        });

        // 20.7 领料单
        Schema::create('material_issues', function (Blueprint $table) {
            $table->id()->comment('领料ID');
            $table->string('issue_no', 32)->unique()->comment('领料单号');
            $table->unsignedBigInteger('production_order_id')->comment('关联工单ID');
            $table->unsignedBigInteger('warehouse_id')->comment('发料仓库');
            $table->string('status', 16)->default('draft')->comment('状态：draft/issued/confirmed');
            $table->date('issue_date')->comment('领料日期');
            $table->unsignedBigInteger('created_by')->nullable()->comment('领用人');
            $table->timestamps();
        });

        // 领料明细
        Schema::create('material_issue_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('material_issue_id')->comment('领料单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->default('')->comment('批次号');
            $table->decimal('qty', 12, 2)->comment('领料数量');
            $table->index('material_issue_id', 'idx_issue');
        });

        // 20.5 盘点单
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id()->comment('盘点ID');
            $table->string('stocktake_no', 32)->unique()->comment('盘点单号');
            $table->unsignedBigInteger('warehouse_id')->comment('盘点仓库');
            $table->string('status', 16)->default('draft')->comment('状态：draft/counting/completed/adjusted');
            $table->date('plan_date')->comment('计划盘点日期');
            $table->timestamp('finish_date')->nullable()->comment('实际完成时间');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->timestamps();
        });

        // 20.6 盘点明细
        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id()->comment('明细ID');
            $table->unsignedBigInteger('stocktake_id')->comment('盘点单ID');
            $table->unsignedBigInteger('material_id')->comment('物料ID');
            $table->string('lot_no', 32)->default('')->comment('批次号');
            $table->decimal('book_qty', 12, 2)->comment('账面数量');
            $table->decimal('actual_qty', 12, 2)->nullable()->comment('实盘数量');
            $table->decimal('diff_qty', 12, 2)->nullable()->comment('差异数量');
            $table->index('stocktake_id', 'idx_stocktake');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktakes');
        Schema::dropIfExists('material_issue_lines');
        Schema::dropIfExists('material_issues');
        Schema::dropIfExists('production_operation_logs');
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('routing_operations');
        Schema::dropIfExists('routings');
        Schema::dropIfExists('work_centers');
    }
};
