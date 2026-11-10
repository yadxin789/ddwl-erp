<?php

namespace App\Support;

use App\Models\Organization\Position;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 行级数据范围（S5 / 规划 §8.4）
 *
 * 规则：岗位 data_scope_type → all / department / self / none。
 * 多岗并集取**最宽**：all > department > self > none。
 * 无岗位、未登录一律按 self（fail-closed，不会因配置缺失而放开全量）。
 */
class DataScopeService
{
    public const ALL = 'all';
    public const DEPARTMENT = 'department';
    public const SELF = 'self';
    public const NONE = 'none';

    private array $cache = [];

    private const RANK = [
        self::NONE => 0,
        self::SELF => 1,
        self::DEPARTMENT => 2,
        self::ALL => 3,
    ];

    public function __construct(private NavigationService $navigation)
    {
    }

    public function forCurrentUser(): string
    {
        return $this->forUser(auth()->user());
    }

    public function forUser(?Authenticatable $user): string
    {
        if (! $user) {
            return self::SELF;
        }

        $key = (string) $user->getAuthIdentifier();
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $nav = $this->navigation->forUser($user);
        $codes = [];
        foreach ($nav['position_keys'] as $positionKey) {
            $code = config('navigation.positions.' . $positionKey . '.code');
            if ($code) {
                $codes[$code] = true;
            }
        }

        if (! $codes) {
            return $this->cache[$key] = self::SELF;
        }

        $types = Position::query()
            ->whereIn('pos_code', array_keys($codes))
            ->pluck('data_scope_type')
            ->filter()
            ->all();

        // 全部岗位都是 none → 不看业务数据（系统管理员）
        if ($types && ! array_diff($types, [self::NONE])) {
            return $this->cache[$key] = self::NONE;
        }

        $best = self::SELF;
        foreach ($types as $type) {
            if (! isset(self::RANK[$type]) || $type === self::NONE) {
                continue;
            }
            if (self::RANK[$type] > self::RANK[$best]) {
                $best = $type;
            }
        }

        return $this->cache[$key] = $best;
    }

    /** 当前账号所属部门（department 范围用） */
    public function departmentId(): ?int
    {
        $id = auth()->user()?->department_id;

        return $id ? (int) $id : null;
    }

    /** 供测试/调试直接看某个账号的范围 */
    public function forget(?int $userId = null): void
    {
        if ($userId === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[(string) $userId]);
    }
}
