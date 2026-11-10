<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 菜单入口可达性（P0-06 / 计划 §7）
 *
 * 背景：导航对"带参路由/未建成页面"是静默跳过的，所以"岗位矩阵全绿"并不能证明入口存在。
 * 这里对 7 个此前失效的菜单项逐个断言：路由存在且无参数、菜单指向的页面能被对应岗位打开。
 * 不 RefreshDatabase，依赖开发库演示账号（与 PageGuardTest 一致）。
 */
class MenuReachabilityTest extends TestCase
{
    /** 此前被导航静默跳过的 7 个菜单项 */
    private const FIXED_MENUS = [
        '我的报工', '收货上架', '送检', 'BOM审批', '我的任务', '文档审核', '文档上传',
    ];

    public function test_fixed_menus_point_to_parameter_free_routes(): void
    {
        $map = config('navigation.menu_items');

        foreach (self::FIXED_MENUS as $label) {
            $this->assertArrayHasKey($label, $map, "菜单项「{$label}」缺少映射");

            $routeName = $map[$label]['route'];
            $this->assertTrue(Route::has($routeName), "菜单项「{$label}」指向不存在的路由 {$routeName}");

            $params = Route::getRoutes()->getByName($routeName)->parameterNames();
            $this->assertSame([], $params, "菜单项「{$label}」仍指向带参路由 {$routeName}");
        }
    }

    public function test_each_fixed_menu_page_opens_for_its_position(): void
    {
        $map = config('navigation.menu_items');

        foreach (self::FIXED_MENUS as $label) {
            $permissions = array_values(array_filter(
                $map[$label]['permissions'] ?? [],
                fn ($p) => $p !== '__auth__'
            ));
            $this->assertNotEmpty($permissions, "菜单项「{$label}」没有声明权限，无法挑测试账号");

            $user = User::all()->first(fn (User $u) => $u->hasAllPermissions($permissions));
            $this->assertNotNull($user, "菜单项「{$label}」找不到同时具备 "
                . implode('+', $permissions) . ' 的演示账号');

            $response = $this->actingAs($user)->get(route($map[$label]['route']));

            $this->assertSame(200, $response->getStatusCode(),
                "菜单项「{$label}」（{$user->username}）打开 "
                . $map[$label]['route'] . ' 返回 ' . $response->getStatusCode());
        }
    }
}
