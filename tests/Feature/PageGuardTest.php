<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Security\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 页面守卫冒烟测试（v1.3 §8 / §10.2）
 *
 * 注意：不 RefreshDatabase —— 依赖现有开发库的演示账号与角色，
 * 否则要重建全部权限/角色/演示数据，单测运行成本过高。
 */
class PageGuardTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    public function test_warehouse_keeper_cannot_open_finance_pages(): void
    {
        $this->actingAsDemo('wh-keeper');

        $response = $this->get('/finance/invoices');

        $response->assertStatus(403);
        $response->assertSee('该页面不属于您的岗位');
    }

    /**
     * G1 发货作业台已上线，仓管的 sales.view 已收回：
     * 仓管只能在自己的发货作业台发货，不能再进销售订单页。
     */
    public function test_warehouse_keeper_cannot_open_sales_orders_after_g1(): void
    {
        $this->actingAsDemo('wh-keeper');

        $this->get('/sales/orders')->assertStatus(403);
        $this->get('/shipping')->assertStatus(200);
    }

    public function test_system_admin_cannot_open_business_pages(): void
    {
        $this->actingAsDemo('admin');

        $this->get('/sales/orders')->assertStatus(403);
        $this->get('/production/orders')->assertStatus(403);
    }

    public function test_unauthorized_access_is_audited(): void
    {
        $this->actingAsDemo('wh-keeper');

        $this->get('/finance/invoices')->assertStatus(403);

        $this->assertTrue(
            SecurityEvent::where('event_type', 'unauthorized_access')
                ->where('user_id', User::where('username', 'wh-keeper')->value('id'))
                ->exists(),
            '越权访问未写入 security_events'
        );
    }

    public function test_warehouse_keeper_can_open_stocktake_page(): void
    {
        $this->actingAsDemo('wh-keeper');

        $this->get('/production/stocktakes')->assertStatus(200);
    }

    public function test_admin_sidebar_renders_position_menu_with_shared_group(): void
    {
        $this->actingAsDemo('admin');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('用户管理');
        $response->assertSee('协同');
    }

    public function test_iqc_sidebar_hides_other_positions_menus(): void
    {
        $this->actingAsDemo('iqc-inspector');

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('用户管理');
        $response->assertDontSee('系统运维');
    }
}
