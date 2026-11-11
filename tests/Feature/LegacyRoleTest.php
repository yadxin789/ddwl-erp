<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P2-02 旧角色/兼容角色回收：巡检命令、菜单快照、迁移演练（默认不改数据）、--force 幂等。
 */
class LegacyRoleTest extends TestCase
{
    public function test_config_has_deadline_rehearsal_and_full_legacy_map(): void
    {
        $this->assertSame('2026-12-31', config('navigation.legacy_role_deadline'));
        $this->assertSame('2026-12-17', config('navigation.legacy_role_rehearsal'));

        $map = config('navigation.legacy_role_map');
        $this->assertCount(7, $map);
        foreach ($map as $legacy => $target) {
            $this->assertArrayHasKey($target, config('navigation.positions'), "{$legacy} 的目标岗位 {$target} 不存在");
        }

        $this->assertSame(['system_admin'], config('navigation.legacy_role_whitelist')['张三']);
        $this->assertCount(4, config('navigation.compat_roles'), '兼容角色应保持 4 个');
    }

    public function test_audit_lists_accounts_and_writes_menu_snapshot(): void
    {
        $snapshot = storage_path('framework/testing/legacy-role-snapshot.json');
        @unlink($snapshot);

        Artisan::call('erp:legacy-role:audit', ['--snapshot' => $snapshot]);
        $out = Artisan::output();
        $this->assertStringContainsString('旧角色回收巡检', $out);
        $this->assertStringContainsString('2026-12-31', $out);
        $this->assertStringContainsString('王五', $out);
        $this->assertStringContainsString('白名单 张三', $out);

        $this->assertFileExists($snapshot);
        $payload = json_decode(file_get_contents($snapshot), true);
        $this->assertSame(7, $payload['summary']['legacy_roles']);
        $this->assertCount(4, $payload['compat']);
        $this->assertArrayHasKey('王五', $payload['accounts']);
        $this->assertNotEmpty($payload['accounts']['王五']['business']);

        @unlink($snapshot);
    }

    public function test_audit_json_and_strict_exit_codes(): void
    {
        Artisan::call('erp:legacy-role:audit', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);
        $this->assertArrayHasKey('summary', $payload);
        $this->assertSame(7, count($payload['legacy']));
        $this->assertSame(4, count($payload['compat']));

        // 当前仍在停用日之前，非白名单绑定存在 → strict 必须红；到期回收后应转绿
        $code = Artisan::call('erp:legacy-role:audit', ['--strict' => true]);
        $pending = $payload['summary']['pending_total'];
        $this->assertSame($pending > 0 ? 1 : 0, $code, 'strict 退出码应与待回收数一致');
    }

    public function test_dry_run_does_not_change_role_bindings(): void
    {
        $user = User::where('username', '钱七')->firstOrFail();
        $before = $user->getRoleNames()->all();

        Artisan::call('erp:legacy-role:migrate');
        $out = Artisan::output();
        $this->assertStringContainsString('dry-run', $out);
        $this->assertSame($before, $user->fresh()->getRoleNames()->all(), '演练不得改变账号角色');
        $this->assertTrue($user->fresh()->hasRole('sales_specialist'));
    }

    public function test_force_migration_switches_legacy_to_position_role_and_is_idempotent(): void
    {
        $user = User::where('username', '钱七')->firstOrFail();
        $this->assertTrue($user->hasRole('sales_specialist'));
        $this->assertTrue($user->hasRole('role_sal_specialist'));

        DB::beginTransaction();
        try {
            Artisan::call('erp:legacy-role:migrate', ['--force' => true]);
            $fresh = $user->fresh();
            $this->assertFalse($fresh->hasRole('sales_specialist'), '旧角色应被摘除');
            $this->assertTrue($fresh->hasRole('role_sal_specialist'), '岗位角色必须保留');

            // 幂等：再跑一次不报错，也不重复摘除/补授
            $code = Artisan::call('erp:legacy-role:migrate', ['--force' => true]);
            $this->assertSame(0, $code);
            $this->assertTrue($user->fresh()->hasRole('role_sal_specialist'));

            // 白名单 admin 不受影响
            $admin = User::where('username', '张三')->firstOrFail();
            $this->assertTrue($admin->hasRole('system_admin'), '白名单账号的旧角色必须保留');
        } finally {
            DB::rollBack();
        }
    }
}
