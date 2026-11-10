<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 菜单/守卫一致性自检（v1.3 §8.4 · M1~M7）
 *
 * 用法：php artisan erp:guard:check
 * 退出码：0 全绿；1 存在阻断级问题（缺共享项 / 无角色 / 名称无映射 / 权限未定义 / 菜单指向带参路由）。
 */
class GuardCheck extends Command
{
    protected $signature = 'erp:guard:check {--verbose-detail : 打印每个岗位的业务项数量}';

    protected $description = '校验岗位菜单配置、名称映射、路由与权限库的一致性';

    public function handle(): int
    {
        $config = config('navigation', []);
        $shared = $config['shared'] ?? [];
        $map = $config['menu_items'] ?? [];
        $definitions = array_merge($config['positions'] ?? [], $config['compat_roles'] ?? []);

        $errors = [];
        $warnings = [];

        // M1：每岗位至少 1 个角色
        foreach ($definitions as $key => $def) {
            if (empty($def['roles'])) {
                $errors[] = "M1 [$key] 未绑定任何角色";
            }
        }

        // M2：共享 11 项必须齐全；M4：名称必须有映射；无重名
        $missingRoutes = [];
        $undefinedPerms = [];
        $definedPerms = Permission::pluck('name')->all();

        foreach ($definitions as $key => $def) {
            $menu = $def['menu'] ?? [];
            $label = $def['name'] ?? $key;

            $missing = array_values(array_diff($shared, $menu));
            if ($missing) {
                $errors[] = "M2 [$label] 缺共享菜单：" . implode('、', $missing);
            }

            $dups = array_keys(array_filter(array_count_values($menu), fn ($c) => $c > 1));
            if ($dups) {
                $errors[] = "M4 [$label] 菜单重名：" . implode('、', $dups);
            }

            foreach ($menu as $name) {
                if (! isset($map[$name])) {
                    $errors[] = "M4 [$label] 菜单项「$name」没有名称→路由→权限映射";
                    continue;
                }

                $routeName = $map[$name]['route'];
                if (! Route::has($routeName)) {
                    $missingRoutes[$routeName][] = $name;
                }

                foreach ($map[$name]['permissions'] ?? [] as $perm) {
                    if ($perm !== '__auth__' && ! in_array($perm, $definedPerms, true)) {
                        $undefinedPerms[$perm][] = $name;
                    }
                }
            }

            if ($this->option('verbose-detail')) {
                $biz = count(array_diff($menu, $shared));
                $this->line(sprintf('  %-12s %-20s 业务 %d + 共享 %d', $key, $label, $biz, count($shared)));
            }
        }

        // M5：岗位台账必须与配置一一对应，且每个岗位至少绑一个存在的角色
        $positionProblems = [];
        $roleNames = Role::pluck('name')->all();
        $positions = \App\Models\Organization\Position::where('status', 1)->get();
        if ($positions->count() !== count($config['positions'] ?? [])) {
            $positionProblems[] = sprintf('岗位表启用 %d 个，配置 %d 个，数量不一致',
                $positions->count(), count($config['positions'] ?? []));
        }
        foreach ($positions as $position) {
            $bound = $position->role_names ?? [];
            if (! $bound) {
                $positionProblems[] = $position->pos_code . ' 未绑定角色';
            }
            foreach (array_diff($bound, $roleNames) as $missingRole) {
                $positionProblems[] = $position->pos_code . ' 绑定了不存在的角色 ' . $missingRole;
            }
        }
        foreach ($positionProblems as $p) {
            $errors[] = 'M5 ' . $p;
        }

        // M6：数据范围必须是可执行枚举，且不能与中文说明同时为空（S5 行级过滤依赖它）
        $scopeTypes = ['all', 'department', 'self', 'none'];
        foreach ($positions as $position) {
            if (! in_array($position->data_scope_type, $scopeTypes, true)) {
                $errors[] = sprintf('M6 %s 数据范围类型非法（%s），可选：%s',
                    $position->pos_code, (string) $position->data_scope_type, implode('/', $scopeTypes));
            }
            if (! $position->data_scope) {
                $warnings[] = 'M6 ' . $position->pos_code . ' 缺少数据范围文字说明（给人看的口径）';
            }
        }

        // M7：菜单项必须映射到无参路由。带参路由无法在导航里生成链接，
        // NavigationService 会静默跳过 —— 结果是"菜单配了、入口没了"（P0-06 的根因）。
        $paramRoutes = [];
        foreach ($map as $name => $item) {
            $routeName = $item['route'] ?? null;
            if (! $routeName || ! Route::has($routeName)) {
                continue;
            }
            $route = Route::getRoutes()->getByName($routeName);
            $params = $route->parameterNames();
            if ($params) {
                $paramRoutes[] = sprintf('M7 菜单项「%s」→ %s 带参数（%s），导航无法生成链接',
                    $name, $routeName, implode(', ', $params));
            }
        }
        foreach ($paramRoutes as $p) {
            $errors[] = $p;
        }

        // 孤儿权限：库里存在但没有任何角色持有
        $usedByMenu = [];
        foreach ($map as $item) {
            foreach ($item['permissions'] ?? [] as $perm) {
                if ($perm !== '__auth__') {
                    $usedByMenu[$perm] = true;
                }
            }
        }
        $granted = [];
        foreach (Role::with('permissions')->get() as $role) {
            foreach ($role->permissions as $perm) {
                $granted[$perm->name] = true;
            }
        }
        $orphans = array_values(array_diff(array_keys($usedByMenu), array_keys($granted)));

        if ($missingRoutes) {
            $warnings[] = '以下菜单指向尚未建成的页面（缺口页，侧边栏会自动跳过）：';
            foreach ($missingRoutes as $route => $names) {
                $warnings[] = "  - $route ← " . implode('、', array_unique($names));
            }
        }
        if ($undefinedPerms) {
            foreach ($undefinedPerms as $perm => $names) {
                $errors[] = '权限库缺少 ' . $perm . '（菜单：' . implode('、', array_unique($names)) . '）';
            }
        }
        if ($orphans) {
            $warnings[] = '以下权限尚无任何角色持有：' . implode('、', $orphans);
        }

        // M8（P2-03）：一个权限横跨 >3 个业务页 = 页面级权限又兼了数据凭据，拆细信号
        $permPages = [];
        foreach ($map as $name => $item) {
            $routeName = $item['route'] ?? '(无路由)';
            foreach ($item['permissions'] ?? [] as $perm) {
                if ($perm === '__auth__') {
                    continue;
                }
                $permPages[$perm][$routeName] = true;
            }
        }
        foreach ($permPages as $perm => $routes) {
            if (count($routes) > 3) {
                $warnings[] = sprintf('M8 权限 %s 跨 %d 个业务页（%s），应拆细',
                    $perm, count($routes), implode('、', array_keys($routes)));
            }
        }

        $this->info(sprintf('岗位/兼容角色：%d 个；共享菜单：%d 项；名称映射：%d 条；权限库：%d 条',
            count($definitions), count($shared), count($map), count($definedPerms)));

        foreach ($warnings as $w) {
            $this->warn($w);
        }

        if ($errors) {
            foreach ($errors as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $this->info('M1~M7 全部通过。');

        return self::SUCCESS;
    }
}
