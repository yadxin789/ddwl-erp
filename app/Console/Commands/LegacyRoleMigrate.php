<?php

namespace App\Console\Commands;

use App\Models\Organization\User;
use Illuminate\Console\Command;

/**
 * P2-02 旧角色回收：把绑定旧角色的账号迁到对应岗位角色，再摘掉旧角色。
 *
 * 默认 dry-run，只打印将要执行的动作；必须显式 --force 才落库。
 * 白名单账号（config('navigation.legacy_role_whitelist')）跳过，留档。
 *
 * 用法：php artisan erp:legacy-role:migrate [--dry-run] [--force]
 */
class LegacyRoleMigrate extends Command
{
    protected $signature = 'erp:legacy-role:migrate
                            {--dry-run : 只打印动作（默认行为）}
                            {--force : 真正把账号迁到岗位角色并摘掉旧角色}';

    protected $description = '把旧角色绑定账号迁移到岗位角色并回收旧角色（默认演练）';

    public function handle(): int
    {
        $map = config('navigation.legacy_role_map', []);
        $whitelist = config('navigation.legacy_role_whitelist', []);
        $force = (bool) $this->option('force');

        $this->warn($force ? '模式：执行（--force），会修改账号角色绑定' : '模式：演练（dry-run），不改数据');

        $assigned = 0;
        $removed = 0;
        $skipped = 0;

        foreach ($map as $legacyRole => $targetKey) {
            $def = config("navigation.positions.{$targetKey}", []);
            $targetRole = $def['roles'][0] ?? null;
            if (! $targetRole) {
                $this->error("目标岗位 {$targetKey} 未配置角色，跳过 {$legacyRole}");

                return 1;
            }

            foreach (User::role($legacyRole)->orderBy('username')->get() as $user) {
                if (in_array($legacyRole, $whitelist[$user->username] ?? [], true)) {
                    $this->line("  跳过 {$user->username}（白名单：{$legacyRole}）");
                    $skipped++;

                    continue;
                }

                $needsTarget = ! $user->hasRole($targetRole);
                $this->line(sprintf('  %s：%s %s → %s，摘除 %s', $user->username,
                    $needsTarget ? '补' : '已有', $targetRole, $targetRole, $legacyRole));

                if ($force) {
                    if ($needsTarget) {
                        $user->assignRole($targetRole);
                        $assigned++;
                    }
                    $user->removeRole($legacyRole);
                    $removed++;
                }
            }
        }

        if ($force) {
            $this->info("完成：补授岗位角色 {$assigned} 个账号，摘除旧角色 {$removed} 个绑定，白名单跳过 {$skipped}。");
            $this->line('复核：php artisan erp:legacy-role:audit --strict');
        } else {
            $this->info('演练结束：未修改数据。确认后执行 php artisan erp:legacy-role:migrate --force');
        }

        return 0;
    }
}
