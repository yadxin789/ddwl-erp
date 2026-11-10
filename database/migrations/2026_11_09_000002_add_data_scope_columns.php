<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-08 / C-01~C-03：补齐行级归属列（缺列不再等于放行）
 *
 * 新增：
 *  - production_orders(department_id, created_by)
 *  - customers(owner_id, department_id)
 *  - suppliers(owner_id, department_id)
 *  - goods_receipts(receiver_id, department_id)
 *  - customer_invoices(owner_id, salesperson_id)
 *  - supplier_bills(owner_id, buyer_id)
 *  - warehouses(department_id)   供 inventory_stock 做"本部门仓"过滤
 *
 * 演示库回填：按岗位把历史单据归到对应部门/责任人（可空，缺失即 fail-closed 不返回）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->index()->after('sales_order_id');
            $table->unsignedBigInteger('created_by')->nullable()->index()->after('department_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->index()->after('payment_terms');
            $table->unsignedBigInteger('department_id')->nullable()->index()->after('owner_id');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->index()->after('lead_time_days');
            $table->unsignedBigInteger('department_id')->nullable()->index()->after('owner_id');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('receiver_id')->nullable()->index()->after('warehouse_id');
            $table->unsignedBigInteger('department_id')->nullable()->index()->after('receiver_id');
        });

        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->index()->after('status');
            $table->unsignedBigInteger('salesperson_id')->nullable()->index()->after('owner_id');
        });

        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->index()->after('status');
            $table->unsignedBigInteger('buyer_id')->nullable()->index()->after('owner_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->index()->after('type');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['department_id', 'created_by']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['owner_id', 'department_id']);
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['owner_id', 'department_id']);
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropColumn(['receiver_id', 'department_id']);
        });
        Schema::table('customer_invoices', function (Blueprint $table) {
            $table->dropColumn(['owner_id', 'salesperson_id']);
        });
        Schema::table('supplier_bills', function (Blueprint $table) {
            $table->dropColumn(['owner_id', 'buyer_id']);
        });
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
    }

    private function backfill(): void
    {
        $user = fn (string $username) => DB::table('users')->where('username', $username)->value('id');
        $dept = fn (string $code) => DB::table('departments')->where('dept_code', $code)->value('id');

        $prodDept = $dept('D-PROD');
        $purDept = $dept('D-PUR');
        $salDept = $dept('D-SAL');
        $whDept = $dept('D-WH');

        // 仓库统一归仓储部（"本部门仓"范围的依据）
        if ($whDept) {
            DB::table('warehouses')->whereNull('department_id')->update(['department_id' => $whDept]);
        }

        // 历史工单：之前没有创建人，归属生产部 + 生产主管（保证生产岗可见，不被 fail-closed 吃空）
        if ($prodDept || $user('prod-supervisor')) {
            DB::table('production_orders')->whereNull('department_id')->update([
                'department_id' => $prodDept,
                'created_by' => $user('prod-supervisor'),
            ]);
        }

        // 历史客户：归销售部 / 销售专员
        DB::table('customers')->whereNull('department_id')->update([
            'department_id' => $salDept,
            'owner_id' => $user('sales'),
        ]);

        // 历史供应商：归采购部 / 采购专员
        DB::table('suppliers')->whereNull('department_id')->update([
            'department_id' => $purDept,
            'owner_id' => $user('purchase'),
        ]);

        // 历史收货单：归仓储部 / 收货员
        DB::table('goods_receipts')->whereNull('department_id')->update([
            'department_id' => $whDept,
            'receiver_id' => $user('wh-receiver'),
        ]);
    }
};
