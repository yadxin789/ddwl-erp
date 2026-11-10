<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->unsignedBigInteger('parent_id')->nullable();
            $t->integer('sort')->default(0);
            $t->timestamps();
        });

        Schema::create('product_brands', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->string('logo', 256)->nullable();
            $t->timestamps();
        });

        Schema::create('product_spus', function (Blueprint $t) {
            $t->id();
            $t->string('spu_no', 32)->unique();
            $t->string('name', 128);
            $t->unsignedBigInteger('category_id')->nullable();
            $t->unsignedBigInteger('brand_id')->nullable();
            $t->string('unit', 16)->nullable();
            $t->decimal('market_price', 12, 2)->default(0);
            $t->decimal('sale_price', 12, 2)->default(0);
            $t->string('status', 16)->default('draft')->comment('draft/on_sale/off_sale');
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('product_skus', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('spu_id');
            $t->string('sku_code', 64)->unique();
            $t->string('spec', 128)->nullable()->comment('规格如 红色/XL');
            $t->decimal('price', 12, 2)->default(0);
            $t->integer('stock')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
        Schema::dropIfExists('product_spus');
        Schema::dropIfExists('product_brands');
        Schema::dropIfExists('product_categories');
    }
};
