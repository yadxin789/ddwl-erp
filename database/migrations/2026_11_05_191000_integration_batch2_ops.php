<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 批次2：生产/质量/采购/销售扩展表（~25张）
 */
return new class extends Migration {
    public function up(): void
    {
        // === 生产扩展 ===
        Schema::create('production_lines', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->unsignedBigInteger('work_center_id')->nullable();
            $t->timestamps();
        });
        Schema::create('production_order_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('production_order_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty_per', 12, 3)->default(0);
            $t->string('unit', 16)->nullable();
            $t->timestamps();
        });
        Schema::create('production_plans', function (Blueprint $t) {
            $t->id();
            $t->string('plan_no', 32)->unique();
            $t->date('plan_date');
            $t->string('status', 16)->default('draft');
            $t->timestamps();
        });
        Schema::create('production_plan_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('plan_id');
            $t->unsignedBigInteger('product_id');
            $t->decimal('plan_qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('production_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('receipt_no', 32)->unique();
            $t->unsignedBigInteger('production_order_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('production_receipt_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('receipt_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('production_reports', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('production_order_id');
            $t->unsignedBigInteger('worker_id')->nullable();
            $t->decimal('good_qty', 12, 2)->default(0);
            $t->decimal('bad_qty', 12, 2)->default(0);
            $t->timestamp('reported_at')->nullable();
            $t->timestamps();
        });
        Schema::create('production_losses', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('production_order_id');
            $t->string('reason', 128);
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('rework_orders', function (Blueprint $t) {
            $t->id();
            $t->string('rework_no', 32)->unique();
            $t->unsignedBigInteger('production_order_id')->nullable();
            $t->string('reason', 256)->nullable();
            $t->string('status', 16)->default('open');
            $t->timestamps();
        });
        Schema::create('material_returns', function (Blueprint $t) {
            $t->id();
            $t->string('return_no', 32)->unique();
            $t->unsignedBigInteger('issue_id')->nullable();
            $t->string('reason', 256)->nullable();
            $t->timestamps();
        });
        Schema::create('material_return_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });

        // === 质量扩展 ===
        Schema::create('defect_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 128);
            $t->string('category', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('defect_records', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('inspection_id')->nullable();
            $t->unsignedBigInteger('defect_code_id')->nullable();
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('quality_inspections', function (Blueprint $t) {
            $t->id();
            $t->string('inspect_no', 32)->unique();
            $t->string('stage', 16);
            $t->string('result', 16)->default('pending');
            $t->timestamps();
        });
        Schema::create('quality_inspection_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('inspection_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('sample_qty', 12, 2)->default(0);
            $t->decimal('qualified_qty', 12, 2)->default(0);
            $t->timestamps();
        });

        // === 采购扩展 ===
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->string('return_no', 32)->unique();
            $t->unsignedBigInteger('purchase_order_id');
            $t->string('reason', 256)->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_return_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('return_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('supplier_evaluations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('supplier_id');
            $t->tinyInteger('quality_score')->default(0);
            $t->tinyInteger('delivery_score')->default(0);
            $t->tinyInteger('price_score')->default(0);
            $t->date('period');
            $t->timestamps();
        });

        // === 销售扩展 ===
        Schema::create('quotation_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('quotation_id');
            $t->unsignedBigInteger('material_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->decimal('price', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('shipment_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('shipment_id');
            $t->unsignedBigInteger('order_line_id');
            $t->decimal('qty', 12, 2)->default(0);
            $t->timestamps();
        });

        // === 库存扩展 ===
        Schema::create('inventory_reservations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('material_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->string('lot_no', 64)->nullable();
            $t->decimal('qty', 12, 2)->default(0);
            $t->string('ref_type', 32)->nullable();
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->string('status', 16)->default('reserved');
            $t->timestamps();
        });
        Schema::create('safe_stocks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('material_id');
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->decimal('min_qty', 12, 2)->default(0);
            $t->decimal('max_qty', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('goods_warn_setting', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('material_id')->nullable();
            $t->decimal('threshold', 12, 2)->default(0);
            $t->string('channel', 64)->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_batches', function (Blueprint $t) {
            $t->id();
            $t->string('batch_no', 64)->unique();
            $t->unsignedBigInteger('material_id');
            $t->date('production_date')->nullable();
            $t->date('expiry_date')->nullable();
            $t->timestamps();
        });
        Schema::create('inventory_documents', function (Blueprint $t) {
            $t->id();
            $t->string('doc_no', 32)->unique();
            $t->string('doc_type', 32);
            $t->unsignedBigInteger('ref_id')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'inventory_documents','inventory_batches','goods_warn_setting','safe_stocks',
            'inventory_reservations','shipment_lines','quotation_lines',
            'supplier_evaluations','purchase_return_lines','purchase_returns',
            'quality_inspection_lines','quality_inspections','defect_records','defect_codes',
            'material_return_lines','material_returns','rework_orders','production_losses',
            'production_reports','production_receipt_lines','production_receipts',
            'production_plan_lines','production_plans','production_order_lines','production_lines',
        ] as $t) Schema::dropIfExists($t);
    }
};
