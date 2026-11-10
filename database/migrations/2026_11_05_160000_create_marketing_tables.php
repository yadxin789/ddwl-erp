<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->string('type', 16)->comment('discount/full_gift/seckill');
            $t->decimal('value', 10, 2)->default(0)->comment('折扣或金额');
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('coupon_no', 32)->unique();
            $t->string('name', 128);
            $t->decimal('amount', 10, 2)->default(0);
            $t->decimal('min_spend', 10, 2)->default(0);
            $t->integer('total_qty')->default(0);
            $t->integer('received_qty')->default(0);
            $t->date('expire_date')->nullable();
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        Schema::create('aftersales_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ticket_no', 32)->unique();
            $t->string('ecom_order_no', 64)->nullable();
            $t->string('customer_name', 64)->nullable();
            $t->string('customer_phone', 32)->nullable();
            $t->string('type', 16)->comment('return/refund/exchange/complaint');
            $t->text('description');
            $t->string('status', 16)->default('open')->comment('open/processing/closed');
            $t->unsignedBigInteger('handler_id')->nullable();
            $t->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('spu_id')->nullable();
            $t->string('buyer_nick', 64)->nullable();
            $t->tinyInteger('rating')->default(5)->comment('1-5星');
            $t->text('content')->nullable();
            $t->boolean('is_hidden')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('aftersales_tickets');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('promotions');
    }
};
