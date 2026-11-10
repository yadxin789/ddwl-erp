<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-08 电商交易闭环：订单归属实例、同步状态、发货回写字段、outbox 重试/死信、对账批次。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecom_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_instance_id')->nullable()->after('platform_id');
            $table->string('sync_status', 16)->default('pulled')->after('status')->comment('pulled/shipped/pushed/error');
            $table->json('raw_payload')->nullable()->after('receiver_address');
            $table->string('tracking_no', 64)->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('pushed_at')->nullable();

            $table->index('platform_instance_id', 'idx_ecom_instance');
            $table->index('sync_status', 'idx_ecom_sync_status');
        });

        Schema::table('ecom_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable()->after('product_name');
            $table->boolean('sku_mapped')->default(false)->after('material_id');
        });

        Schema::table('integration_outbox', function (Blueprint $table) {
            $table->timestamp('next_retry_at')->nullable()->after('published_at');
            $table->string('last_error', 255)->nullable()->after('next_retry_at');
            $table->timestamp('dead_at')->nullable()->after('last_error');
        });

        Schema::create('ecom_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('platform_instance_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('paid_count')->default(0);
            $table->unsignedInteger('shipped_count')->default(0);
            $table->decimal('header_amount', 14, 2)->default(0)->comment('订单头金额合计');
            $table->decimal('item_amount', 14, 2)->default(0)->comment('订单行金额合计');
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->string('result', 16)->default('matched')->comment('matched/mismatch');
            $table->json('summary_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['platform_instance_id', 'period_start', 'period_end'], 'idx_ecom_recon_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_reconciliation_runs');

        Schema::table('integration_outbox', function (Blueprint $table) {
            $table->dropColumn(['next_retry_at', 'last_error', 'dead_at']);
        });

        Schema::table('ecom_order_items', function (Blueprint $table) {
            $table->dropColumn(['material_id', 'sku_mapped']);
        });

        Schema::table('ecom_orders', function (Blueprint $table) {
            $table->dropIndex('idx_ecom_instance');
            $table->dropIndex('idx_ecom_sync_status');
            $table->dropColumn([
                'platform_instance_id', 'sync_status', 'raw_payload', 'tracking_no',
                'last_error', 'pulled_at', 'pushed_at',
            ]);
        });
    }
};
