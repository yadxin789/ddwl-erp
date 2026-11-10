<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Support\NavigationService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * S8 验收矩阵（简化版）：逐个演示账号验证
 *  1) 侧边栏渲染出 11 项共享菜单 + 本岗菜单数量 > 0；
 *  2) 菜单里出现的每个页面 URL 都能打开（不是 403/500）；
 *  3) 菜单里没有的业务页面，直接 GET 必须 403。
 */
class PositionMatrixTest extends TestCase
{
    /** 覆盖 29 个岗位（全部有演示账号） */
    private array $accounts = [
        'admin',
        'wh-supervisor', 'wh-keeper', 'wh-receiver',
        'purchase', 'pur-manager',
        'sales', 'sal-manager', 'sal-cs',
        'engineer', 'eng-sw', 'eng-me', 'pkg-designer', 'id-designer', 'rnd-manager',
        'quality', 'iqc-inspector', 'ipqc-inspector', 'fqc-inspector',
        'prod-supervisor', 'prod-leader', 'operator',
        'admin-assistant', 'adm-manager',
        'accountant', 'cashier',
        'ecom-operator', 'ecom-cs',
        'gm',
    ];

    /** 探针页面：任何岗位只应有其中一部分（用真实路由名） */
    private array $probes = [
        'sales.orders',
        'purchase.orders',
        'production.orders',
        'finance.cashbook',
        'users.index',
    ];

    public function test_each_position_sees_only_its_own_pages(): void
    {
        $shared = config('navigation.shared');
        $this->assertCount(11, $shared);

        $matrix = [];

        foreach ($this->accounts as $username) {
            $user = User::where('username', $username)->first();
            $this->assertNotNull($user, "演示账号缺失：{$username}");
            $this->actingAs($user);
            app(NavigationService::class);   // 无状态，但保证容器可用

            $nav = app(NavigationService::class)->forUser($user);
            $this->assertFalse($nav['no_position'], "{$username} 未匹配到岗位");
            $this->assertNotEmpty($nav['business'], "{$username} 业务菜单为空");

            $sharedNames = array_column($nav['shared'], 'name');
            if ($nav['home']) {
                $sharedNames[] = $nav['home']['name'];   // 工作台是共享首项，归入 home 分组
            }
            $this->assertSame([], array_values(array_diff($shared, $sharedNames)),
                "{$username} 共享菜单缺项：" . implode('、', array_diff($shared, $sharedNames)));

            // 打开本岗菜单里的每个页面
            $menuUrls = [];
            foreach (array_merge($nav['business'], $nav['shared']) as $item) {
                $menuUrls[$item['url']] = true;
                $response = $this->get($item['url']);
                $status = $response->getStatusCode();
                $this->assertNotSame(403, $status, "{$username} 菜单项「{$item['name']}」被判 403（{$item['url']}）");
                $this->assertLessThan(500, $status, "{$username} 菜单项「{$item['name']}」报错 {$status}（{$item['url']}）");
            }

            // 探针：不在菜单里的页面必须 403
            $denied = 0;
            foreach ($this->probes as $routeName) {
                if (! Route::has($routeName)) {
                    continue;
                }
                $url = route($routeName);
                if (isset($menuUrls[$url])) {
                    continue;
                }
                $this->assertSame(403, $this->get($url)->getStatusCode(),
                    "{$username} 越权可进 {$routeName}");
                $denied++;
            }

            $matrix[$username] = [
                'position' => $nav['position_name'],
                'business' => count($nav['business']),
                'shared' => count($nav['shared']),
                'denied' => $denied,
            ];

            // 侧边栏实渲染：11 项共享菜单全部出现在 HTML 里
            $home = $this->get('/');
            $home->assertOk();
            foreach ($shared as $name) {
                $home->assertSee($name, false);
            }
        }

        // 至少覆盖到 11 个有菜单的岗位；管理员被拒的业务页不少于 3 个
        $this->assertGreaterThanOrEqual(11, count($matrix));
        $this->assertGreaterThanOrEqual(3, $matrix['admin']['denied']);
    }
}
