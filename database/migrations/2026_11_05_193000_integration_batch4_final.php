<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次4：收尾表（~45张）
 * 去重映射：brands=product_brands / chat_messages=system_chat_messages / employees=hr_employees
 * notifications=system_notifications / projects=rnd_projects / quotations=sales_quotes
 * receipts=goods_receipts / inventory_flows=inventory_transactions / locations=warehouse_locations
 * 这些不重复建，只建真正缺失的。
 */
return new class extends Migration {
    public function up(): void
    {
        // === 审计扩展 ===
        Schema::create('audit_approval_flows', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('approval_id'); $t->string('action', 32);
            $t->text('detail')->nullable(); $t->timestamps();
        });
        Schema::create('audit_export_logs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('module', 64); $t->string('file_name', 256)->nullable();
            $t->timestamps();
        });
        Schema::create('audit_order_statuses', function (Blueprint $t) {
            $t->id(); $t->string('order_no', 64); $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32); $t->timestamps();
        });
        Schema::create('audit_product_changes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->string('field', 64);
            $t->text('old_value')->nullable(); $t->text('new_value')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_reconciliation_diffs', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reconcile_id');
            $t->string('item', 128); $t->decimal('expected', 14, 2)->default(0);
            $t->decimal('actual', 14, 2)->default(0); $t->timestamps();
        });
        Schema::create('authorization_exceptions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('resource', 256); $t->text('reason')->nullable(); $t->timestamps();
        });
        Schema::create('authorization_failures', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ip', 64)->nullable(); $t->string('resource', 256); $t->timestamps();
        });

        // === 商品/价格 ===
        Schema::create('product_attributes', function (Blueprint $t) {
            $t->id(); $t->string('name', 128); $t->string('attr_type', 32)->default('text');
            $t->timestamps();
        });
        Schema::create('product_attribute_values', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('attribute_id'); $t->string('value', 256); $t->timestamps();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id');
            $t->string('url', 512); $t->integer('sort_order')->default(0); $t->timestamps();
        });
        Schema::create('product_versions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id');
            $t->integer('version')->default(1); $t->json('snapshot')->nullable(); $t->timestamps();
        });
        Schema::create('product_listing_templates', function (Blueprint $t) {
            $t->id(); $t->string('name', 128); $t->string('platform', 32);
            $t->json('fields')->nullable(); $t->timestamps();
        });
        Schema::create('goods_publish_records', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id'); $t->string('platform', 32);
            $t->string('status', 16)->default('pending'); $t->timestamps();
        });
        Schema::create('price_rules', function (Blueprint $t) {
            $t->id(); $t->string('name', 128); $t->string('rule_type', 32);
            $t->json('conditions')->nullable(); $t->timestamps();
        });
        Schema::create('price_change_requests', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('product_id');
            $t->decimal('old_price', 12, 2)->default(0);
            $t->decimal('new_price', 12, 2)->default(0);
            $t->string('status', 16)->default('pending'); $t->timestamps();
        });
        Schema::create('price_change_tasks', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('request_id');
            $t->timestamp('effective_at')->nullable(); $t->string('status', 16)->default('pending');
            $t->timestamps();
        });

        // === 营销售后 ===
        Schema::create('after_sale_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('aftersale_id');
            $t->unsignedBigInteger('order_item_id')->nullable();
            $t->decimal('qty', 12, 2)->default(0); $t->timestamps();
        });
        Schema::create('order_after_sales', function (Blueprint $t) {
            $t->id(); $t->string('aftersale_no', 32)->unique();
            $t->unsignedBigInteger('order_id'); $t->string('type', 32);
            $t->decimal('amount', 12, 2)->default(0); $t->string('status', 16)->default('open');
            $t->timestamps();
        });
        Schema::create('coupon_usages', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('coupon_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
        });
        Schema::create('promotion_platform_mappings', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('promotion_id'); $t->string('platform', 32);
            $t->string('platform_promo_id', 128)->nullable(); $t->timestamps();
        });
        Schema::create('refund_records', function (Blueprint $t) {
            $t->id(); $t->string('refund_no', 32)->unique();
            $t->unsignedBigInteger('order_id'); $t->decimal('amount', 12, 2)->default(0);
            $t->string('status', 16)->default('pending'); $t->timestamps();
        });
        Schema::create('refund_flows', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('refund_id'); $t->string('step', 64);
            $t->text('detail')->nullable(); $t->timestamps();
        });
        Schema::create('return_shipments', function (Blueprint $t) {
            $t->id(); $t->string('shipment_no', 32)->unique();
            $t->unsignedBigInteger('aftersale_id')->nullable();
            $t->string('tracking_no', 128)->nullable(); $t->timestamps();
        });

        // === 采购扩展 ===
        Schema::create('procurement_requests', function (Blueprint $t) {
            $t->id(); $t->string('request_no', 32)->unique();
            $t->string('department', 64)->nullable(); $t->string('status', 16)->default('draft');
            $t->timestamps();
        });
        Schema::create('procurement_approvals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('request_id');
            $t->unsignedBigInteger('approver_id')->nullable();
            $t->string('status', 16)->default('pending'); $t->timestamps();
        });
        Schema::create('procurement_orders', function (Blueprint $t) {
            $t->id(); $t->string('po_no', 32)->unique();
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('draft'); $t->timestamps();
        });
        Schema::create('procurement_inbounds', function (Blueprint $t) {
            $t->id(); $t->string('inbound_no', 32)->unique();
            $t->unsignedBigInteger('po_id')->nullable();
            $t->date('inbound_date')->nullable(); $t->timestamps();
        });

        // === 财务/税务 ===
        Schema::create('exchange_rates', function (Blueprint $t) {
            $t->id(); $t->string('from_currency', 8); $t->string('to_currency', 8);
            $t->decimal('rate', 14, 6)->default(1); $t->date('rate_date'); $t->timestamps();
        });
        Schema::create('tax_entities', function (Blueprint $t) {
            $t->id(); $t->string('code', 32)->unique(); $t->string('name', 128);
            $t->string('type', 32)->nullable(); $t->timestamps();
        });
        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tax_entity_id');
            $t->string('tax_type', 32); $t->decimal('rate', 6, 3)->default(0);
            $t->timestamps();
        });
        Schema::create('finance_diff_approvals', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('reconcile_id');
            $t->decimal('diff_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('pending'); $t->timestamps();
        });

        // === 安全/多租户 ===
        Schema::create('ip_whitelists', function (Blueprint $t) {
            $t->id(); $t->string('ip_range', 128)->unique();
            $t->string('description', 256)->nullable(); $t->timestamps();
        });
        Schema::create('user_data_scopes', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id');
            $t->string('scope_type', 32); $t->json('scope_ids')->nullable(); $t->timestamps();
        });
        Schema::create('user_shop_limit', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('shop_id'); $t->timestamps();
        });

        // === 企微/语音 ===
        Schema::create('wecom_files', function (Blueprint $t) {
            $t->id(); $t->string('media_id', 256)->unique();
            $t->string('file_name', 256); $t->string('file_path', 512)->nullable();
            $t->timestamps();
        });
        Schema::create('wecom_oauth_states', function (Blueprint $t) {
            $t->id(); $t->string('state', 128)->unique();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('voice_sessions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('room_id');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable(); $t->timestamps();
        });

        // === 事件/物流/通用 ===
        Schema::create('event_logs', function (Blueprint $t) {
            $t->id(); $t->string('event_type', 128);
            $t->string('handler', 128)->nullable();
            $t->string('status', 16)->default('processed');
            $t->text('error')->nullable(); $t->timestamps();
        });
        Schema::create('logistics_shipments', function (Blueprint $t) {
            $t->id(); $t->string('shipment_no', 32)->unique();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('carrier', 64)->nullable(); $t->string('tracking_no', 128)->nullable();
            $t->timestamps();
        });
        Schema::create('logistics_traces', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('shipment_id');
            $t->text('trace')->nullable(); $t->timestamp('traced_at')->nullable(); $t->timestamps();
        });
        Schema::create('express_providers', function (Blueprint $t) {
            $t->id(); $t->string('code', 32)->unique(); $t->string('name', 128);
            $t->string('api_url', 512)->nullable(); $t->timestamps();
        });
        Schema::create('routing_headers', function (Blueprint $t) {
            $t->id(); $t->string('routing_no', 32)->unique();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('version', 16)->default('v1'); $t->timestamps();
        });
        Schema::create('media_library', function (Blueprint $t) {
            $t->id(); $t->string('name', 256); $t->string('path', 512);
            $t->string('mime_type', 64)->nullable(); $t->bigInteger('size_bytes')->default(0);
            $t->timestamps();
        });
        Schema::create('system_goods', function (Blueprint $t) {
            $t->id(); $t->string('goods_code', 64)->unique();
            $t->string('name', 128); $t->string('category', 64)->nullable(); $t->timestamps();
        });
        Schema::create('system_ship_records', function (Blueprint $t) {
            $t->id(); $t->string('ship_no', 32)->unique();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamp('shipped_at')->nullable(); $t->timestamps();
        });
        Schema::create('items', function (Blueprint $t) {
            $t->id(); $t->string('item_code', 64)->unique();
            $t->string('name', 128); $t->string('unit', 16)->nullable(); $t->timestamps();
        });
        Schema::create('item_categories', function (Blueprint $t) {
            $t->id(); $t->string('code', 32)->unique(); $t->string('name', 128); $t->timestamps();
        });
        Schema::create('item_suppliers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('item_id');
            $t->unsignedBigInteger('supplier_id'); $t->decimal('price', 12, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'item_suppliers','item_categories','items','system_ship_records','system_goods',
            'media_library','routing_headers','express_providers','logistics_traces','logistics_shipments',
            'event_logs','voice_sessions','wecom_oauth_states','wecom_files',
            'user_shop_limit','user_data_scopes','ip_whitelists',
            'finance_diff_approvals','tax_rates','tax_entities','exchange_rates',
            'procurement_inbounds','procurement_orders','procurement_approvals','procurement_requests',
            'return_shipments','refund_flows','refund_records','promotion_platform_mappings',
            'coupon_usages','order_after_sales','after_sale_items',
            'price_change_tasks','price_change_requests','price_rules',
            'goods_publish_records','product_listing_templates','product_versions',
            'product_images','product_attribute_values','product_attributes',
            'authorization_failures','authorization_exceptions',
            'audit_reconciliation_diffs','audit_product_changes','audit_order_statuses',
            'audit_export_logs','audit_approval_flows',
        ] as $t) Schema::dropIfExists($t);
    }
};
