<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Support\NavigationService;
use Tests\TestCase;

/**
 * 原则：侧边栏不向无权限用户提供入口，因此任何侧边栏链接点进去都不应出现 403。
 * 该用例逐个演示账号展开侧边栏（岗位 + 共享菜单）并请求每个入口。
 */
class SidebarEntryNoForbiddenTest extends TestCase
{
    public function test_every_sidebar_entry_is_reachable_for_each_demo_user(): void
    {
        $users = User::where('status', 1)->orderBy('id')->get();
        $this->assertNotEmpty($users);

        $failures = [];

        foreach ($users as $user) {
            $this->actingAs($user);

            $nav = app(NavigationService::class)->forUser($user);
            if ($nav['no_position']) {
                continue;
            }

            $items = array_filter(array_merge(
                [$nav['home']],
                $nav['business'],
                $nav['shared']
            ));

            // 页内链接只在边界账号上展开（系统管理员无业务权限、总经理跨域看板、无建单权的主管），
            // 其余账号只验侧边栏入口，避免用例过长。
            $crawlLinks = in_array($user->name, ['张三', '高远', '吴十'], true);

            foreach ($items as $item) {
                $response = $this->get($item['url']);
                if ($response->getStatusCode() === 403) {
                    $failures[] = sprintf('%s（%s）→ %s', $user->name, $item['name'], $item['url']);
                    continue;
                }

                // 页面内的站内链接也应可达：列表点“查看/详情”不能落到 403
                if ($response->getStatusCode() !== 200 || ! $crawlLinks) {
                    continue;
                }

                preg_match_all('/href="([^"]+)"/', (string) $response->getContent(), $matches);
                foreach (array_unique($matches[1] ?? []) as $href) {
                    if (! str_starts_with($href, url('/'))) {
                        continue;
                    }
                    $path = parse_url($href, PHP_URL_PATH);
                    if (! $path || $path === '/' || $path === parse_url($item['url'], PHP_URL_PATH)) {
                        continue;
                    }
                    if (preg_match('#\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$#i', $path)) {
                        continue;
                    }

                    $linked = $this->get($path);
                    if ($linked->getStatusCode() === 403) {
                        $failures[] = sprintf('%s（%s 页内链接）→ %s', $user->name, $item['name'], $path);
                    }
                }
            }
        }

        $this->assertSame([], $failures, "以下侧边栏入口点击后返回 403：\n" . implode("\n", $failures));
    }
}
