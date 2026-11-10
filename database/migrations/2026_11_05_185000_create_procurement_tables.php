<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('procurement_plans', function (Blueprint $t) {
            $t->id();
            $t->string('plan_no', 32)->unique();
            $t->string('title', 128);
            $t->date('plan_date');
            $t->decimal('total_amount', 14, 2)->default(0);
            $t->string('status', 16)->default('draft');
            $t->timestamps();
        });

        Schema::create('procurement_arrivals', function (Blueprint $t) {
            $t->id();
            $t->string('arrival_no', 32)->unique();
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->date('arrival_date');
            $t->string('status', 16)->default('expected');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_arrivals');
        Schema::dropIfExists('procurement_plans');
    }
};
