<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次3：电商/平台/物流/运维扩展表（~30张）
 */
return new class extends Migration {
    public function up(): void
    {
        // === 电商订单扩展 ===
        Schema::create('shops', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('shop_name', 128);
            $t->string('shop_url', 512)->nullable();
            $t->string('status', 16)->default('active');
            $t->timestamps();
        });
        Schema::create('shop_credentials', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shop_id');
            $t->string('app_key', 256)->nullable();
            $t->string('app_secret', 512)->nullable();
            $t->text('access_token')->nullable();
            $t->timestamps();
        });
        Schema::create('order_addresses', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('receiver_name', 64);
            $t->string('receiver_phone', 32);
            $t->string('province', 64)->nullable();
            $t->string('city', 64)->nullable();
            $t->string('district', 64)->nullable();
            $t->string('detail', 256)->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('sku', 64)->nullable();
            $t->string('product_name', 256);
            $t->decimal('qty', 12, 2)->default(1);
            $t->decimal('price', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('order_payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('pay_method', 32);
            $t->decimal('amount', 12, 2)->default(0);
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_packages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('tracking_no', 128)->nullable();
            $t->string('carrier', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('order_status_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32);
            $t->string('operator', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('order_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('type', 32)->comment('discount/shipping/tax');
            $t->decimal('amount', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('packages', function (Blueprint $t) {
            $t->id();
            $t->string('package_no', 32)->unique();
            $t->decimal('weight', 10, 2)->nullable();
            $t->string('size', 64)->nullable();
            $t->timestamps();
        });

        // === 平台扩展 ===
        Schema::create('platform_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('account_name', 128);
            $t->string('account_id', 128)->nullable();
            $t->string('status', 16)->default('active');
            $t->timestamps();
        });
        Schema::create('platform_capabilities', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('feature', 64);
            $t->boolean('supported')->default(false);
            $t->timestamps();
        });
        Schema::create('platform_orders', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('platform_order_no', 128);
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('status', 32)->nullable();
            $t->timestamps();
        });
        Schema::create('platform_products', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('platform_spu_id', 128);
            $t->unsignedBigInteger('local_spu_id')->nullable();
            $t->timestamps();
        });
        Schema::create('platform_skus', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('platform_sku_id', 128);
            $t->unsignedBigInteger('local_sku_id')->nullable();
            $t->timestamps();
        });
        Schema::create('platform_settlements', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->date('period');
            $t->decimal('amount', 14, 2)->default(0);
            $t->string('status', 16)->default('pending');
            $t->timestamps();
        });

        // === 物流扩展 ===
        Schema::create('logistics_companies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->string('tracking_url', 512)->nullable();
            $t->timestamps();
        });
        Schema::create('logistics_tracks', function (Blueprint $t) {
            $t->id();
            $t->string('tracking_no', 128);
            $t->string('status', 64)->nullable();
            $t->text('trace')->nullable();
            $t->timestamps();
        });
        Schema::create('logistics_bills', function (Blueprint $t) {
            $t->id();
            $t->string('bill_no', 32)->unique();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('logistics_exceptions', function (Blueprint $t) {
            $t->id();
            $t->string('tracking_no', 128);
            $t->string('type', 64);
            $t->text('detail')->nullable();
            $t->string('status', 16)->default('open');
            $t->timestamps();
        });
        Schema::create('shipping_methods', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->decimal('freight', 10, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('shipping_templates', function (Blueprint $t) {
            $t->id();
            $t->string('name', 128);
            $t->json('rules')->nullable();
            $t->timestamps();
        });
        Schema::create('express_company', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->timestamps();
        });

        // === 同步扩展 ===
        Schema::create('shop_sync_cursors', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shop_id');
            $t->string('data_type', 32);
            $t->string('cursor', 256)->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        Schema::create('stock_sync_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shop_id')->nullable();
            $t->integer('success_count')->default(0);
            $t->integer('fail_count')->default(0);
            $t->timestamp('synced_at')->nullable();
            $t->timestamps();
        });
        Schema::create('token_refresh_logs', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->boolean('success')->default(false);
            $t->text('message')->nullable();
            $t->timestamps();
        });
        Schema::create('webhook_event_logs', function (Blueprint $t) {
            $t->id();
            $t->string('platform', 32);
            $t->string('event_type', 128);
            $t->json('payload')->nullable();
            $t->string('status', 16)->default('received');
            $t->timestamps();
        });

        // === 运维扩展 ===
        Schema::create('database_backups', function (Blueprint $t) {
            $t->id();
            $t->string('file_name', 256);
            $t->bigInteger('size_bytes')->default(0);
            $t->timestamp('backed_up_at')->nullable();
            $t->timestamps();
        });
        Schema::create('export_tasks', function (Blueprint $t) {
            $t->id();
            $t->string('type', 64);
            $t->string('file_path', 512)->nullable();
            $t->string('status', 16)->default('pending');
            $t->unsignedBigInteger('requested_by')->nullable();
            $t->timestamps();
        });
        Schema::create('feature_switches', function (Blueprint $t) {
            $t->id();
            $t->string('code', 64)->unique();
            $t->boolean('enabled')->default(false);
            $t->text('description')->nullable();
            $t->timestamps();
        });
        Schema::create('file_storage_configs', function (Blueprint $t) {
            $t->id();
            $t->string('disk', 32);
            $t->string('bucket', 128)->nullable();
            $t->string('region', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('exception_cases', function (Blueprint $t) {
            $t->id();
            $t->string('ref_type', 64);
            $t->unsignedBigInteger('ref_id');
            $t->string('reason', 256);
            $t->string('status', 16)->default('open');
            $t->timestamps();
        });
        Schema::create('system_alerts', function (Blueprint $t) {
            $t->id();
            $t->string('level', 16)->default('info');
            $t->string('title', 256);
            $t->text('content')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });
        Schema::create('account_lockouts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip', 64)->nullable();
            $t->timestamp('locked_until')->nullable();
            $t->timestamps();
        });
        Schema::create('permission_change_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('action', 32);
            $t->text('detail')->nullable();
            $t->timestamps();
        });
        Schema::create('authorization_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action', 64);
            $t->string('ip', 64)->nullable();
            $t->boolean('granted')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'authorization_logs','permission_change_logs','account_lockouts','system_alerts',
            'exception_cases','file_storage_configs','feature_switches','export_tasks','database_backups',
            'webhook_event_logs','token_refresh_logs','stock_sync_logs','shop_sync_cursors',
            'express_company','shipping_templates','shipping_methods','logistics_exceptions',
            'logistics_bills','logistics_tracks','logistics_companies',
            'platform_settlements','platform_skus','platform_products','platform_orders',
            'platform_capabilities','platform_accounts',
            'packages','order_adjustments','order_status_logs','order_packages',
            'order_payments','order_items','order_addresses','shop_credentials','shops',
        ] as $t) Schema::dropIfExists($t);
    }
};
