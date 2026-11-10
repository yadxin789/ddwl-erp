<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 销售线索
        Schema::create('crm_leads', function (Blueprint $t) {
            $t->id();
            $t->string('lead_no', 32)->unique();
            $t->string('company_name', 128);
            $t->string('contact_name', 64)->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('email', 128)->nullable();
            $t->string('source', 32)->nullable()->comment('官网/展会/转介绍/广告');
            $t->string('status', 16)->default('new')->comment('new/contacted/qualified/lost/converted');
            $t->unsignedBigInteger('owner_id')->nullable()->comment('负责人');
            $t->text('remark')->nullable();
            $t->timestamps();
        });

        // 商机
        Schema::create('crm_opportunities', function (Blueprint $t) {
            $t->id();
            $t->string('op_no', 32)->unique();
            $t->string('name', 128);
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->decimal('expected_amount', 14, 2)->default(0);
            $t->integer('probability')->default(50)->comment('赢单概率%');
            $t->date('expected_close_date')->nullable();
            $t->string('stage', 16)->default('prospecting')->comment('prospecting/negotiation/won/lost');
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->timestamps();
        });

        // 跟进记录
        Schema::create('crm_follow_ups', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('lead_id')->nullable();
            $t->unsignedBigInteger('opportunity_id')->nullable();
            $t->unsignedBigInteger('user_id');
            $t->string('type', 16)->default('call')->comment('call/visit/email/meeting');
            $t->text('content');
            $t->timestamp('followed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_follow_ups');
        Schema::dropIfExists('crm_opportunities');
        Schema::dropIfExists('crm_leads');
    }
};
