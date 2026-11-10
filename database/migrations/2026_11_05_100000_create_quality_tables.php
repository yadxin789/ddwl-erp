<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 检验模板（IQC/IPQC/FQC/OQC）
        Schema::create('inspection_templates', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->string('stage', 16)->comment('IQC/IPQC/FQC/OQC');
            $t->json('check_items')->comment('检验项JSON');
            $t->string('standard', 64)->nullable()->comment('AQL标准');
            $t->boolean('status')->default(true);
            $t->timestamps();
        });

        // 检验记录
        Schema::create('inspection_records', function (Blueprint $t) {
            $t->id();
            $t->string('record_no', 32)->unique();
            $t->string('stage', 16)->comment('IQC/IPQC/FQC/OQC');
            $t->unsignedBigInteger('material_id')->nullable();
            $t->unsignedBigInteger('ref_id')->nullable()->comment('关联单据ID');
            $t->string('ref_type', 32)->nullable()->comment('goods_receipt/production_order等');
            $t->decimal('sample_qty', 12, 2)->default(0);
            $t->decimal('qualified_qty', 12, 2)->default(0);
            $t->decimal('defect_qty', 12, 2)->default(0);
            $t->string('result', 16)->default('pending')->comment('pending/accepted/rejected');
            $t->unsignedBigInteger('inspector_id')->nullable();
            $t->text('remark')->nullable();
            $t->timestamp('inspected_at')->nullable();
            $t->timestamps();
        });

        // 不良缺陷代码
        Schema::create('defects', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique();
            $t->string('name', 128);
            $t->string('category', 32)->nullable()->comment('外观/功能/包装等');
            $t->text('description')->nullable();
            $t->timestamps();
        });

        // 纠正措施单
        Schema::create('corrective_actions', function (Blueprint $t) {
            $t->id();
            $t->string('ca_no', 32)->unique();
            $t->unsignedBigInteger('inspection_record_id');
            $t->string('problem', 500);
            $t->string('root_cause', 500)->nullable();
            $t->string('action', 500)->nullable();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->date('due_date')->nullable();
            $t->string('status', 16)->default('open');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('defects');
        Schema::dropIfExists('inspection_records');
        Schema::dropIfExists('inspection_templates');
    }
};
