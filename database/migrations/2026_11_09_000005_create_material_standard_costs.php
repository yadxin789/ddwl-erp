<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-02 标准成本（D-14 / D-20 / §9.4）
 * - material_standard_costs：按版本维护，同一物料同一时点仅 1 个 active，禁止覆盖历史
 * - inventory_transactions 增 std_cost / actual_cost / price_variance（采购价差单列，不摊入单笔销售成本）
 * - sales_order_lines 增 std_cost_amount（发货时点 active 版本 × 数量）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_standard_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->unsignedInteger('version')->default(1)->comment('同一物料的版本号，从 1 递增');
            $table->decimal('standard_cost', 12, 4)->comment('标准成本单价');
            $table->string('currency', 8)->default('CNY');
            $table->date('effective_from')->comment('生效日');
            $table->date('effective_to')->nullable()->comment('失效日（被新版本取代时写）');
            $table->string('status', 16)->default('draft')->comment('draft/active/superseded');
            $table->string('source', 16)->default('manual')->comment('manual/demo/fallback');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'version'], 'uk_material_version');
            $table->index(['material_id', 'status', 'effective_from'], 'idx_material_status_from');
            $table->index('status');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('std_cost', 12, 4)->nullable()->after('to_status')->comment('发生时点标准成本');
            $table->decimal('actual_cost', 12, 4)->nullable()->after('std_cost')->comment('实际单价（采购价等）');
            $table->decimal('price_variance', 14, 2)->nullable()->after('actual_cost')->comment('价差 =(实际-标准)×数量');
        });

        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->decimal('std_cost_amount', 14, 2)->nullable()->after('unit_price')
                ->comment('标准成本结转金额（发货时点 active 版本 × 数量）');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn('std_cost_amount');
        });
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['std_cost', 'actual_cost', 'price_variance']);
        });
        Schema::dropIfExists('material_standard_costs');
    }
};
