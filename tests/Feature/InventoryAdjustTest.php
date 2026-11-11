<?php

namespace Tests\Feature;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryStock;
use App\Models\Organization\User;
use App\Services\InventoryAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P0-04 库存调整两段化 + 500 元阈值（D-16 / 计划 §9.3）
 *
 * 不 RefreshDatabase：用开发库演示数据，所有写操作包在事务里、结束时回滚，
 * 保证测试不污染开发库（见 P0 执行记录 §6.1 的 403/404 说明）。
 */
class InventoryAdjustTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    private function roleHas(string $roleName, string $permission): bool
    {
        return Role::findByName($roleName)->hasPermissionTo($permission);
    }

    private function routeMiddleware(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "路由不存在：{$routeName}");

        return array_values(array_filter($route->gatherMiddleware(), 'is_string'));
    }

    /* ---------- 权限矩阵：发起 ≠ 审批 ---------- */

    public function test_request_and_approve_are_split_between_roles(): void
    {
        $this->assertTrue($this->roleHas('role_wh_keeper', 'inventory.adjust.request'), '仓管应能发起调整');
        $this->assertFalse($this->roleHas('role_wh_keeper', 'inventory.adjust.approve'), '仓管不得自批调整');

        $this->assertTrue($this->roleHas('role_wh_supervisor', 'inventory.adjust.approve'), '仓库主管应能审批');
        $this->assertTrue($this->roleHas('role_prod_leader', 'inventory.adjust.request'), '生产组长应能发起调整');
        $this->assertFalse($this->roleHas('role_prod_leader', 'inventory.adjust.approve'));

        $this->assertContains('permission:inventory.adjust.request', $this->routeMiddleware('inventory.adjustments.store'));
        $this->assertNotContains('permission:inventory.adjust.approve', $this->routeMiddleware('inventory.adjustments.store'));

        $this->assertContains('permission:inventory.adjust.approve', $this->routeMiddleware('inventory.adjustments.approve'));
        $this->assertContains('permission:inventory.adjust.request', $this->routeMiddleware('production.stocktakes.adjust'));
    }

    public function test_warehouse_keeper_cannot_approve_via_http(): void
    {
        $this->actingAsDemo('王五');

        $this->get('/inventory/adjustments')->assertStatus(200);

        // 无审批权：即便单据存在也应 403（权限中间件先于控制器）
        $this->post('/inventory/adjustments/1/approve')->assertStatus(403);
        $this->post('/inventory/adjustments/1/reject', ['remark' => '自批尝试'])->assertStatus(403);
    }

    public function test_supervisor_sees_approval_actions_on_page(): void
    {
        $this->actingAsDemo('李四');

        $this->get('/inventory/adjustments')
            ->assertStatus(200)
            ->assertSee('待审批')
            ->assertSee('库存调整')
            ->assertDontSee('审批权在仓库主管');
    }

    /* ---------- 阈值行为：低于阈值自动通过、超阈值不动库存 ---------- */

    public function test_low_value_adjustment_is_auto_approved_and_applied(): void
    {
        $service = app(InventoryAdjustmentService::class);

        [$stock, $unit] = $this->stockWithUnitCost($service);
        $before = (float) $stock->qty;

        // 让金额明确小于阈值：delta × unit < 500（演示库有 0.0035 元的物料，不能把单价截断成 0.01）
        $delta = $this->deltaForAmount($unit, 1.0);
        $this->assertGreaterThan(0, $delta);

        DB::beginTransaction();
        try {
            $adj = $service->requestManual($stock->id, $before + $delta, '测试：低于阈值自动通过', 1);

            $this->assertSame('auto_approved', $adj->status);
            $this->assertTrue($adj->auto_approved);
            $this->assertLessThan((float) $adj->threshold, (float) $adj->amount);

            $stock->refresh();
            $this->assertEqualsWithDelta($before + $delta, (float) $stock->qty, 0.0001, '自动通过的调整应立即改库存');

            // 流水有留痕，ref_type 指向调整单
            $this->assertDatabaseHas('inventory_transactions', [
                'ref_type' => 'adjustment',
                'ref_id' => $adj->id,
                'txn_type' => 'adjust',
            ]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_high_value_adjustment_stays_pending_until_approved(): void
    {
        $service = app(InventoryAdjustmentService::class);

        [$stock, $unit] = $this->stockWithUnitCost($service);
        $before = (float) $stock->qty;

        // 让金额明确超过阈值（目标 600 元 > 阈值 500 元）
        $delta = $this->deltaForAmount($unit, $service->threshold() * 1.2);

        DB::beginTransaction();
        try {
            $adj = $service->requestManual($stock->id, $before + $delta, '测试：超阈值需审批', 1);

            $this->assertSame('pending', $adj->status);
            $this->assertFalse($adj->auto_approved);
            $this->assertGreaterThanOrEqual((float) $adj->threshold, (float) $adj->amount);

            $stock->refresh();
            $this->assertEqualsWithDelta($before, (float) $stock->qty, 0.0001, '待审批时库存不得变动');
            $this->assertDatabaseMissing('inventory_transactions', [
                'ref_type' => 'adjustment',
                'ref_id' => $adj->id,
            ]);

            // 主管批准 → 生效
            $approver = User::where('username', '李四')->firstOrFail();
            $approved = $service->approve($adj->id, $approver->id, '同意调整');

            $this->assertSame('approved', $approved->status);
            $stock->refresh();
            $this->assertEqualsWithDelta($before + $delta, (float) $stock->qty, 0.0001, '批准后库存应变动');
            $this->assertDatabaseHas('inventory_transactions', [
                'ref_type' => 'adjustment',
                'ref_id' => $adj->id,
            ]);

            // 重复批准幂等：不重复写流水、数量不再叠加
            $again = $service->approve($adj->id, $approver->id, '重复点击');
            $this->assertSame('approved', $again->status);
            $stock->refresh();
            $this->assertEqualsWithDelta($before + $delta, (float) $stock->qty, 0.0001, '重复批准不得二次改库存');
            $this->assertSame(1, DB::table('inventory_transactions')
                ->where('ref_type', 'adjustment')->where('ref_id', $adj->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_reject_keeps_stock_untouched(): void
    {
        $service = app(InventoryAdjustmentService::class);

        [$stock, $unit] = $this->stockWithUnitCost($service);
        $before = (float) $stock->qty;
        $delta = $this->deltaForAmount($unit, $service->threshold() * 1.2);

        DB::beginTransaction();
        try {
            $adj = $service->requestManual($stock->id, $before + $delta, '测试：驳回不动库存', 1);
            $this->assertSame('pending', $adj->status);

            $service->reject($adj->id, 1, '数据来源不清，驳回');
            $this->assertSame('void', $adj->refresh()->status);

            $stock->refresh();
            $this->assertEqualsWithDelta($before, (float) $stock->qty, 0.0001, '驳回后库存不得变动');
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 反推"恰好产生目标金额"的数量差（向上取整到 4 位小数，保证金额 ≥ 目标）
     */
    private function deltaForAmount(float $unit, float $targetAmount): float
    {
        return round(ceil(($targetAmount / $unit) * 100) / 100, 2);
    }

    /**
     * 找一个"有库存 + 能算出单位成本"的库存行；演示库没有则跳过（不假通过）。
     * @return array{0: InventoryStock, 1: float}
     */
    private function stockWithUnitCost(InventoryAdjustmentService $service): array
    {
        $candidates = InventoryStock::with('material')
            ->where('qty', '>', 0)
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($candidates as $stock) {
            $unit = $service->unitCost((int) $stock->material_id);
            if ($unit !== null && $unit > 0) {
                return [$stock, $unit];
            }
        }

        $this->markTestSkipped('演示库没有可计价（cost_price/采购价）的库存行，无法验证阈值口径');
    }
}
