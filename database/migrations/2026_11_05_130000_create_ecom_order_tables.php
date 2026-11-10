<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 电商平台
        Schema::create('ecom_platforms', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique()->comment('taobao/jd/pdd/douyin/1688');
            $t->string('name', 64);
            $t->string('app_key', 128)->nullable();
            $t->string('app_secret', 256)->nullable();
            $t->string('shop_url', 256)->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // 电商订单
        Schema::create('ecom_orders', function (Blueprint $t) {
            $t->id();
            $t->string('ecom_order_no', 64)->unique()->comment('平台订单号');
            $t->unsignedBigInteger('platform_id');
            $t->string('buyer_nick', 64)->nullable();
            $t->string('receiver_name', 64)->nullable();
            $t->string('receiver_phone', 32)->nullable();
            $t->string('receiver_address', 256)->nullable();
            $t->decimal('total_amount', 12, 2)->default(0);
            $t->decimal('pay_amount', 12, 2)->default(0);
            $t->string('status', 20)->default('pending')->comment('pending/paid/shipped/completed/refund/closed');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamps();
        });

        // 电商订单项
        Schema::create('ecom_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ecom_order_id');
            $t->string('outer_sku', 64)->nullable()->comment('外部SKU编码');
            $t->string('product_name', 256);
            $t->integer('qty')->default(1);
            $t->decimal('price', 12, 2)->default(0);
            $t->foreign('ecom_order_id')->references('id')->on('ecom_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecom_order_items');
        Schema::dropIfExists('ecom_orders');
        Schema::dropIfExists('ecom_platforms');
    }
};
