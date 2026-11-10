<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_requisitions', function (Blueprint $t) {
            $t->id();
            $t->string('req_no', 32)->unique()->comment('申请单号 PR+日期+流水');
            $t->string('title', 128)->comment('申请标题');
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedBigInteger('requester_id')->comment('申请人');
            $t->date('need_date')->nullable()->comment('需求日期');
            $t->string('status', 20)->default('draft')->comment('draft/submitted/approved/ordered/rejected');
            $t->text('remark')->nullable();
            $t->unsignedBigInteger('approver_id')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_requisition_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('requisition_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 14, 3)->default(0);
            $t->string('unit', 16)->nullable();
            $t->decimal('estimated_price', 14, 4)->nullable();
            $t->string('remark')->nullable();
            $t->foreign('requisition_id')->references('id')->on('purchase_requisitions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requisition_lines');
        Schema::dropIfExists('purchase_requisitions');
    }
};
