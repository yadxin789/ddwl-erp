<?php

namespace App\Support;

use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * 岗位侧边栏菜单服务（唯一菜单出口）
 *
 * 规则来源：《岗位-菜单-页面规划-v1.3》§5 / §8.1（L1 菜单守卫）
 *  - 菜单 = 岗位配置里的"名称数组"，逐项按 menu_items 映射成 路由 + 权限；
 *  - 共享 11 项写入每个岗位数组，缺项由 erp:guard:check 报错，本服务不做兜底补全；
 *  - 名称 → 路由不存在（缺口页未建）→ 跳过，不渲染死链接；
 *  - 权限不满足 → 跳过（不灰显，避免暴露其他岗位页面）；
 *  - 无岗位匹配 → 三个分组全空（fail-closed），sidebar 提示"未绑定岗位"。
 */
class NavigationService
{
    /** @var array<string, array> */
    private array $cache = [];

    public function forCurrentUser(): array
    {
        $user = auth()->user();

        return $this->forUser($user);
    }

    public function forUser(?Authenticatable $user): array
    {
        $key = $user ? (string) $user->getAuthIdentifier() : '__guest__';
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->build($user);
    }

    private function build(?Authenticatable $user): array
    {
        $config = config('navigation', []);
        $shared = $config['shared'] ?? [];
        $definitions = array_merge($config['positions'] ?? [], $config['compat_roles'] ?? []);
        $legacy = $config['legacy_role_map'] ?? [];

        $result = [
            'position_keys' => [],
            'position_name' => null,
            'department' => null,
            'no_position' => true,
            'home' => null,
            'business' => [],
            'shared' => [],
            'skipped' => [],
        ];

        if (! $user) {
            return $result;
        }

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];

        // 岗位匹配：角色名直接命中 + 旧角色名经 legacy_role_map 折算
        $matchedKeys = [];
        foreach ($definitions as $key => $def) {
            $roles = $def['roles'] ?? [];
            if (array_intersect($roleNames, $roles)) {
                $matchedKeys[] = $key;
                continue;
            }
            foreach ($roleNames as $role) {
                if (($legacy[$role] ?? null) === $key) {
                    $matchedKeys[] = $key;
                    break;
                }
            }
        }

        if (! $matchedKeys) {
            return $result;
        }

        $result['position_keys'] = $matchedKeys;
        $first = $definitions[$matchedKeys[0]];
        $result['position_name'] = $first['name'] ?? null;
        $result['department'] = $first['department'] ?? null;
        $result['no_position'] = false;

        // 多岗并集：先按第一个岗位的顺序，再补其余岗位独有项
        $names = [];
        foreach ($matchedKeys as $key) {
            foreach ($definitions[$key]['menu'] ?? [] as $name) {
                $names[$name] = true;
            }
        }
        $names = array_keys($names);

        foreach ($names as $name) {
            $item = $this->resolve($name, $user, $shared);
            if ($item === null) {
                $result['skipped'][] = $name;
                continue;
            }

            if ($name === ($shared[0] ?? '工作台')) {
                $result['home'] = $item;
            } elseif (in_array($name, $shared, true)) {
                $result['shared'][] = $item;
            } else {
                $result['business'][] = $item;
            }
        }

        return $result;
    }

    /**
     * 名称 → 可渲染菜单项；不可渲染返回 null。
     */
    private function resolve(string $name, Authenticatable $user, array $shared): ?array
    {
        $map = config('navigation.menu_items', []);
        if (! isset($map[$name])) {
            return null;   // 名称无映射：配置错误，由 erp:guard:check 报告
        }

        $routeName = $map[$name]['route'] ?? null;
        if (! $routeName || ! Route::has($routeName)) {
            return null;   // 缺口页未建：跳过，不渲染死链接
        }

        // 需要路由参数的页面（如 xxx.show）不能直接生成 URL，跳过；
        // 这类页面必须有自己的列表页入口（§6 页面表里都配了列表路由）。
        $route = Route::getRoutes()->getByName($routeName);
        if ($route && $route->parameterNames()) {
            return null;
        }

        $permissions = $map[$name]['permissions'] ?? [];
        if ($permissions && ! $this->allows($user, $permissions)) {
            return null;   // 权限不满足：不渲染（不灰显）
        }

        return [
            'name' => $name,
            'route' => $routeName,
            'url' => route($routeName),
            'icon' => config('navigation.icons.' . $name, config('navigation.default_icon', 'fa-circle-dot')),
            'permissions' => $permissions,
            'badge' => $this->badge($name, $user),
        ];
    }

    /**
     * 侧边栏统计徽章（对齐参考项目 layouts/app.blade.php 的研发口径）。
     * 返回 null 表示该菜单不显示徽章；0 值也返回 0，由视图决定是否隐藏。
     */
    private function badge(string $name, Authenticatable $user): ?int
    {
        $userId = (int) $user->getAuthIdentifier();

        $value = match ($name) {
            '研发项目', '我的项目列表' => RndProject::mine($userId)->count(),
            '我的任务' => $this->pendingExecutionCount($userId) + $this->pendingAuditCount($userId),
            '文档上传' => $this->pendingExecutionCount($userId),
            '待审核任务' => $this->pendingAuditCount($userId),
            '文档审核' => RndProjectDocument::withoutGlobalScopes()
                ->where('audit_status', 'pending')
                ->whereHas('task', fn ($q) => $q->where('auditor_id', $userId))
                ->count(),
            '我的待办' => \App\Models\Office\TodoItem::query()
                ->where('user_id', $userId)
                ->where('is_finished', false)
                ->count(),
            default => null,
        };

        return $value;
    }

    private function pendingExecutionCount(int $userId): int
    {
        return RndProjectTask::query()
            ->where('executor_id', $userId)
            ->whereIn('status', ['pending', 'overdue', 'rejected'])
            // 侧边栏渲染时 DataScopeService 会反过来调用 NavigationService，必须绕开全局范围避免递归
            ->whereHas('project', fn ($q) => $q->withoutGlobalScope('data_scope')->where('status', '!=', 'suspended'))
            ->count();
    }

    private function pendingAuditCount(int $userId): int
    {
        return RndProjectTask::query()
            ->where('auditor_id', $userId)
            ->whereIn('status', ['submitted', 'overdue'])
            ->whereHas('project', fn ($q) => $q->withoutGlobalScope('data_scope')->where('status', '!=', 'suspended'))
            ->count();
    }

    /**
     * 任一权限满足即可（权限集合来自文档的 "view/create/edit" 简写）。
     */
    private function allows(Authenticatable $user, array $permissions): bool
    {
        if (in_array('__auth__', $permissions, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($permission && method_exists($user, 'can') && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
