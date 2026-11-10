<?php

namespace App\Console\Commands;

use App\Models\Organization\User;
use App\Support\NavigationService;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

/**
 * P2-02 旧角色回收巡检（Q5：2026-12-31 停用）
 *
 * 覆盖两类"过渡角色"：
 *  - legacy_role_map 的 7 个历史角色（system_admin / warehouse_* / *_specialist / engineer / quality_supervisor）
 *  - compat_roles 的 4 个兼容角色（项目创建人 / 任务执行人 / 任务审核人 / 支援人员）
 *
 * 用法：php artisan erp:legacy-role:audit [--json] [--strict] [--snapshot=路径]
 * 退出码：0 正常；--strict 且仍有非白名单旧角色绑定时 1。
 */
class LegacyRoleAudit extends Command
{
    protected $signature = 'erp:legacy-role:audit
                            {--json : 以 JSON 输出，便于流水线解析}
                            {--strict : 仍有非白名单绑定则 exit 1}
                            {--snapshot= : 把逐账号菜单快照写入指定路径}';

    protected $description = '盘点仍在绑定旧角色/兼容角色的账号，输出菜单快照与回收倒计时';

    public function handle(): int
    {
        $map = config('navigation.legacy_role_map', []);
        $compat = config('navigation.compat_roles', []);
        $whitelist = config('navigation.legacy_role_whitelist', []);
        $deadline = config('navigation.legacy_role_deadline');
        $rehearsal = config('navigation.legacy_role_rehearsal');

        $daysLeft = $deadline ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($deadline)->startOfDay(), false) : null;

        $legacyRows = [];
        $pending = 0;
        foreach ($map as $legacyRole => $targetKey) {
            $users = User::role($legacyRole)->orderBy('username')->get(['id', 'username', 'name']);
            $whitelisted = [];
            $bound = [];
            foreach ($users as $user) {
                $isWhite = in_array($legacyRole, $whitelist[$user->username] ?? [], true);
                if ($isWhite) {
                    $whitelisted[] = $user->username;
                } else {
                    $bound[] = $user->username;
                    $pending++;
                }
            }

            $legacyRows[] = [
                'role' => $legacyRole,
                'target_key' => $targetKey,
                'target_role' => $this->targetRoleName($targetKey),
                'bound' => $bound,
                'whitelisted' => $whitelisted,
                'target_assigned' => $this->allHaveTargetRole($users, $targetKey),
            ];
        }

        $compatRows = [];
        foreach ($compat as $key => $def) {
            $roles = $def['roles'] ?? [];
            $users = User::whereHas('roles', fn ($q) => $q->whereIn('name', $roles))->orderBy('username')->get(['id', 'username', 'name']);
            $compatRows[] = [
                'key' => $key,
                'name' => $def['name'] ?? $key,
                'legacy_of' => $def['legacy_of'] ?? '',
                'bound' => $users->pluck('username')->all(),
                'menu' => $def['menu'] ?? [],
            ];
            $pending += $users->count();
        }

        $summary = [
            'deadline' => $deadline,
            'rehearsal' => $rehearsal,
            'days_left' => $daysLeft,
            'legacy_roles' => count($map),
            'legacy_bound_accounts' => array_sum(array_map(fn ($r) => count($r['bound']) + count($r['whitelisted']), $legacyRows)),
            'legacy_pending' => array_sum(array_map(fn ($r) => count($r['bound']), $legacyRows)),
            'compat_roles' => count($compat),
            'compat_bound_accounts' => array_sum(array_map(fn ($r) => count($r['bound']), $compatRows)),
            'pending_total' => $pending,
        ];

        if ($this->option('snapshot')) {
            $this->writeSnapshot($this->option('snapshot'), $summary, $legacyRows, $compatRows);
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => $summary,
                'legacy' => $legacyRows,
                'compat' => $compatRows,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $this->option('strict') && $pending > 0 ? 1 : 0;
        }

        $this->info(sprintf(
            '旧角色回收巡检：截止 %s（剩 %s 天，演练 %s）',
            $deadline ?: '未配置',
            $daysLeft === null ? '?' : $daysLeft,
            $rehearsal ?: '未配置'
        ));

        foreach ($legacyRows as $row) {
            $line = sprintf('%s → %s：', $row['role'], $row['target_key']);
            $parts = [];
            if ($row['bound']) {
                $parts[] = '待回收 ' . implode('、', $row['bound']);
            }
            if ($row['whitelisted']) {
                $parts[] = '白名单 ' . implode('、', $row['whitelisted']);
            }
            if (! $row['bound'] && ! $row['whitelisted']) {
                $parts[] = '无绑定';
            }
            if (! $row['target_assigned'] && $row['bound']) {
                $parts[] = '⚠ 目标岗位未绑定';
            }
            $this->line('  ' . $line . implode('；', $parts));
        }

        foreach ($compatRows as $row) {
            $this->line(sprintf('  [兼容] %s（%s）：%s', $row['name'], $row['key'],
                $row['bound'] ? '待回收 ' . implode('、', $row['bound']) : '无绑定'));
        }

        $this->line('');
        $this->info(sprintf(
            '合计：旧角色 %d 个 / 绑定 %d 个账号 / 待回收 %d；兼容角色 %d 个 / 绑定 %d 个账号；待回收合计 %d',
            $summary['legacy_roles'], $summary['legacy_bound_accounts'], $summary['legacy_pending'],
            $summary['compat_roles'], $summary['compat_bound_accounts'], $summary['pending_total']
        ));

        if ($this->option('strict') && $pending > 0) {
            $this->error("仍有 {$pending} 个账号绑定旧角色/兼容角色，未达到 2026-12-31 停用目标。");

            return 1;
        }

        return 0;
    }

    private function targetRoleName(string $key): string
    {
        $def = config("navigation.positions.{$key}", []);

        return $def['roles'][0] ?? $key;
    }

    private function allHaveTargetRole($users, string $key): bool
    {
        $target = $this->targetRoleName($key);
        foreach ($users as $user) {
            if (! $user->hasRole($target)) {
                return false;
            }
        }

        return true;
    }

    private function writeSnapshot(string $path, array $summary, array $legacyRows, array $compatRows): void
    {
        $nav = app(NavigationService::class);
        $users = [];

        foreach ($legacyRows as $row) {
            foreach (array_merge($row['bound'], $row['whitelisted']) as $username) {
                $user = User::where('username', $username)->first();
                if ($user) {
                    $n = $nav->forUser($user);
                    $users[$username] = [
                        'roles' => $user->getRoleNames()->all(),
                        'position_name' => $n['position_name'],
                        'business' => array_map(fn ($i) => $i['name'] . '|' . $i['url'], $n['business']),
                        'shared' => array_map(fn ($i) => $i['name'] . '|' . $i['url'], $n['shared']),
                    ];
                }
            }
        }

        $payload = [
            'generated_at' => now()->toDateTimeString(),
            'summary' => $summary,
            'legacy' => $legacyRows,
            'compat' => $compatRows,
            'accounts' => $users,
        ];

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->line("菜单快照已写入：{$path}");
    }
}
