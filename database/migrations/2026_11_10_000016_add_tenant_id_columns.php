<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-4 多租户隔离：先给"用户 / 客户 / 供应商 / 销售订单"加 tenant_id。
 * 平台自有账号（tenant_id 为空）仍看全量；带租户的账号只见本租户。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('department_id');
            $table->index('tenant_id', 'idx_users_tenant');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id', 'idx_customers_tenant');
        });
        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id', 'idx_suppliers_tenant');
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id', 'idx_sales_orders_tenant');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('idx_users_tenant')->dropColumn('tenant_id'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropIndex('idx_customers_tenant')->dropColumn('tenant_id'));
        Schema::table('suppliers', fn (Blueprint $t) => $t->dropIndex('idx_suppliers_tenant')->dropColumn('tenant_id'));
        Schema::table('sales_orders', fn (Blueprint $t) => $t->dropIndex('idx_sales_orders_tenant')->dropColumn('tenant_id'));
    }
};
