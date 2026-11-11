<?php

namespace Tests\Feature;

use App\Models\MasterData\Customer;
use App\Models\Organization\User;
use App\Models\Sales\SalesOrder;
use App\Models\System\Tenant;
use App\Support\TenancyContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** P3-4 多租户隔离验收。 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_customer_and_sales_order_are_isolated_between_tenants(): void
    {
        $a = Tenant::create(['code' => 'TNT-A', 'name' => '租户 A', 'status' => 'active']);
        $b = Tenant::create(['code' => 'TNT-B', 'name' => '租户 B', 'status' => 'active']);

        $sales = User::where('username', '钱七')->firstOrFail();
        $manager = User::where('username', '罗娟')->firstOrFail();
        $sales->forceFill(['tenant_id' => $a->id])->save();
        $manager->forceFill(['tenant_id' => $b->id])->save();

        $customerA = Customer::create([
            'customer_code' => 'TNT-C-A', 'customer_name' => '租户A客户', 'status' => 1,
            'owner_id' => $sales->id, 'tenant_id' => $a->id,
        ]);
        $customerB = Customer::create([
            'customer_code' => 'TNT-C-B', 'customer_name' => '租户B客户', 'status' => 1,
            'owner_id' => $manager->id, 'tenant_id' => $b->id,
        ]);

        SalesOrder::create([
            'order_no' => 'TNT-SO-A', 'customer_id' => $customerA->id, 'order_date' => today(),
            'status' => 'draft', 'total_amount' => 100, 'salesperson_id' => $sales->id, 'tenant_id' => $a->id,
        ]);
        SalesOrder::create([
            'order_no' => 'TNT-SO-B', 'customer_id' => $customerB->id, 'order_date' => today(),
            'status' => 'draft', 'total_amount' => 200, 'salesperson_id' => $manager->id, 'tenant_id' => $b->id,
        ]);

        $this->actingAs($sales)->get(route('customers.index'))
            ->assertOk()->assertSee('租户A客户')->assertDontSee('租户B客户');
        $this->actingAs($sales)->get(route('sales.orders'))
            ->assertOk()->assertSee('TNT-SO-A')->assertDontSee('TNT-SO-B');

        $this->actingAs($manager)->get(route('customers.index'))
            ->assertOk()->assertSee('租户B客户')->assertDontSee('租户A客户');
        $this->actingAs($manager)->get(route('sales.orders'))
            ->assertOk()->assertSee('TNT-SO-B')->assertDontSee('TNT-SO-A');

        // 路由模型绑定同样受租户 scope 影响：A 的销售打不开 B 的订单
        $orderB = SalesOrder::withoutGlobalScopes()->where('order_no', 'TNT-SO-B')->firstOrFail();
        $this->actingAs($sales)->get(route('sales.orders.show', $orderB))->assertNotFound();
    }

    public function test_new_records_inherit_current_tenant_and_platform_admin_sees_all(): void
    {
        $a = Tenant::create(['code' => 'TNT-INH', 'name' => '继承租户', 'status' => 'active']);
        $context = app(TenancyContext::class);
        $context->set($a->id);
        $created = Customer::create([
            'customer_code' => 'TNT-C-INH', 'customer_name' => '自动继承客户', 'status' => 1,
        ]);
        $this->assertSame($a->id, (int) $created->tenant_id);
        $this->assertNull(Customer::where('customer_code', 'ACC-C01')->first());
        $this->assertNotNull(Customer::withoutGlobalScopes()->where('customer_code', 'ACC-C01')->first());

        // 平台账号（tenant_id 为空）上下文清空后可见全部
        $context->set(null);
        $this->assertSame(2, Customer::whereIn('customer_code', ['TNT-C-INH', 'ACC-C01'])->count());
        $this->assertTrue(Customer::where('customer_code', 'TNT-C-INH')->exists());
    }
}
