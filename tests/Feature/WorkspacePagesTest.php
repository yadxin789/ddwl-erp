<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Models\Production\MaterialIssueRequest;
use App\Models\Production\ProductionOrder;
use Tests\TestCase;

/**
 * S6 前段作业页冒烟（v1.3 §4.21 / §4.3 / G1 G2 G3 G9 G6）
 *
 * 覆盖两类事实：
 *  1) 本岗页面能正常打开（控制器 + Blade 无致命错误）；
 *  2) 越岗访问仍 403（守卫没有被新页面绕过）。
 */
class WorkspacePagesTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    public function test_warehouse_keeper_pages_open(): void
    {
        $this->actingAsDemo('wh-keeper');

        $this->get('/shipping')->assertStatus(200);
        $this->get('/warehouse')->assertStatus(200);
        $this->get('/inventory/adjustments')->assertStatus(200);
    }

    public function test_production_leader_pages_open(): void
    {
        $this->actingAsDemo('prod-supervisor');

        $this->get('/production/issue-requests')->assertStatus(200);
        $this->get('/production/issue-requests/create')->assertStatus(200);
    }

    public function test_shared_self_service_pages_open_for_any_position(): void
    {
        $this->actingAsDemo('iqc-inspector');

        $this->get('/files/resources')->assertStatus(200);
        $this->get('/project/my')->assertStatus(200);
        $this->get('/profile/wecom')->assertStatus(200);
    }

    /**
     * 仓管不发领料申请（申请权在生产组长），越岗访问必须 403 而不是 500/200。
     */
    public function test_warehouse_keeper_cannot_create_issue_request(): void
    {
        $this->actingAsDemo('wh-keeper');

        $this->get('/production/issue-requests')->assertStatus(403);
    }

    /**
     * S4 岗位化工作台：指标按岗位裁剪，管理员首页不出现任何业务数字。
     */
    public function test_dashboard_metrics_are_scoped_by_position(): void
    {
        // IQC：只看待检，不该出现销售/财务口径
        $this->actingAsDemo('iqc-inspector');
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('待检记录');
        $response->assertDontSee('应收未收');
        $response->assertDontSee('进行中销售订单');

        // 销售专员：看销售与客户，不该看到应付
        $this->actingAsDemo('sales');
        $response = $this->get('/');
        $response->assertSee('进行中销售订单');
        $response->assertDontSee('应付未付');

        // 管理员：只看运维规模，无业务指标
        $this->actingAsDemo('admin');
        $response = $this->get('/');
        $response->assertSee('账号数');
        $response->assertDontSee('应收未收');
        $response->assertDontSee('进行中生产工单');
    }

    /**
     * S6b 报表 / 编制 / 图档 / 公告 / 驾驶舱：按权限开合，管理员看不到业务驾驶舱。
     */
    public function test_report_and_supporting_pages_respect_position_guards(): void
    {
        // 销售专员：报表中心与销售分析可看
        $this->actingAsDemo('sales');
        $this->get('/reports')->assertStatus(200);
        $this->get('/reports/sales')->assertStatus(200);

        // 仓管：报表与他无关
        $this->actingAsDemo('wh-keeper');
        $this->get('/reports/sales')->assertStatus(403);
        $this->get('/hr/positions')->assertStatus(403);
        $this->get('/dashboard/exec')->assertStatus(403);

        // 行政（HR专员）不持 hr.position.manage / announcement.manage，同样被挡
        $this->actingAsDemo('admin-assistant');
        $this->get('/hr/positions')->assertStatus(403);
        $this->get('/announcements')->assertStatus(200);

        // 管理员不进业务驾驶舱（管理员只管运维）
        $this->actingAsDemo('admin');
        $this->get('/dashboard/exec')->assertStatus(403);
    }

    /**
     * 新建页面全部可渲染（用 Gate 放行，验证视图与查询无运行时错误）。
     */
    public function test_new_pages_render_without_errors(): void
    {
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $this->actingAsDemo('admin');

        foreach ([
            '/dashboard/exec',
            '/reports',
            '/reports/sales',
            '/reports/quality',
            '/reports/supplier',
            '/reports/finance',
            '/hr/positions',
            '/announcements',
            '/design/pkg-files',
            '/design/id-files',
            '/finance/payments',
            '/finance/cashbook',
            '/finance/bank-reconcile',
            '/finance/arap',
            '/finance/notes',
            '/project-templates',
            '/support',
        ] as $uri) {
            $this->get($uri)->assertStatus(200);
        }
    }

    /**
     * 经营驾驶舱只读渲染（用 Gate 放行验证视图本身可用，不改数据）。
     */
    public function test_exec_dashboard_renders_readonly_cockpit(): void
    {
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $this->actingAsDemo('admin');

        $this->get('/dashboard/exec')
            ->assertStatus(200)
            ->assertSee('只读页面')
            ->assertSee('进行中销售订单');
    }

    /**
     * S7 后台管理四页：管理员可进，业务岗一律 403（管理员只管运维）。
     */
    public function test_admin_back_office_pages_open_only_for_system_admin(): void
    {
        $this->actingAsDemo('admin');

        $this->get('/admin/departments')->assertStatus(200);
        $this->get('/admin/positions')->assertStatus(200);
        $this->get('/admin/permissions')->assertStatus(200);
        $this->get('/admin/ai-guide')->assertStatus(200);

        $this->actingAsDemo('wh-keeper');
        $this->get('/admin/departments')->assertStatus(403);
        $this->get('/admin/positions')->assertStatus(403);
        $this->get('/admin/permissions')->assertStatus(403);
        $this->get('/admin/ai-guide')->assertStatus(403);
    }

    /**
     * 无 BOM 的工单：申请直接生效（approved），不进入审批队列（D-13 的"未超耗免审批"）。
     */
    public function test_issue_request_without_bom_is_auto_approved(): void
    {
        $this->actingAsDemo('prod-supervisor');

        // bom_id = 0 表示该工单未挂 BOM（列 NOT NULL 无默认值，测试库也无 BOM 数据）
        $order = ProductionOrder::create([
            'order_no' => 'WO-MIR-TEST-' . uniqid(),
            'product_material_id' => \App\Models\MasterData\Material::query()->value('id'),
            'bom_id' => 0,
            'plan_qty' => 10,
            'status' => 'in_progress',
            'start_date' => now()->toDateString(),
        ]);

        $materialId = \App\Models\MasterData\Material::query()->value('id');

        $response = $this->post('/production/issue-requests', [
            'production_order_id' => $order->id,
            'purpose' => '单测：首件试产',
            'material_id' => [$materialId],
            'qty' => [5],
        ]);

        $response->assertRedirect(route('production.issue-requests'));
        $response->assertSessionHas('success');

        $request = MaterialIssueRequest::where('production_order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($request);
        $this->assertSame('approved', $request->status);
        $this->assertFalse((bool) $request->has_over_bom);
    }
}
