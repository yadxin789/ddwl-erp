<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P2-03 权限颗粒度：quality.view / material.view 已拆成页面级权限，路由按页授权，guard 有 M8 告警。
 */
class PermissionGranularityTest extends TestCase
{
    public function test_new_permissions_exist_and_old_ones_are_removed(): void
    {
        foreach (['quality.inspection.view', 'quality.template.view', 'quality.defect.view',
            'material.list', 'material.read'] as $name) {
            $this->assertTrue(Permission::where('name', $name)->exists(), "缺少权限 {$name}");
        }

        $this->assertFalse(Permission::where('name', 'quality.view')->exists(), 'quality.view 应已删除');
        $this->assertFalse(Permission::where('name', 'material.view')->exists(), 'material.view 应已删除');
    }

    public function test_quality_pages_are_gated_one_by_one(): void
    {
        // 仓管：有检验记录权限，没有检验模板/缺陷代码页权限
        $keeper = User::where('username', 'wh-keeper')->firstOrFail();
        $this->assertTrue($keeper->hasPermissionTo('quality.inspection.view'));
        $this->assertFalse($keeper->hasPermissionTo('quality.template.view'));

        $this->actingAs($keeper)->get('/quality/templates')->assertStatus(403);
        $this->actingAs($keeper)->get('/quality/defects')->assertStatus(403);
        $this->actingAs($keeper)->get('/quality/inspections')->assertStatus(200);

        // 品质主管：三页都能进
        $qc = User::where('username', 'quality')->firstOrFail();
        $this->actingAs($qc)->get('/quality/templates')->assertStatus(200);
        $this->actingAs($qc)->get('/quality/defects')->assertStatus(200);
        $this->actingAs($qc)->get('/quality/inspections')->assertStatus(200);
    }

    public function test_material_list_permission_gates_material_page(): void
    {
        $engineer = User::where('username', 'engineer')->firstOrFail();
        $this->assertTrue($engineer->hasPermissionTo('material.list'));
        $this->actingAs($engineer)->get('/materials')->assertStatus(200);

        // 总经理没有物料页权限，但业务角色仍可用 material.read 做引用读取（这里只断言页面被拒）
        $gm = User::where('username', 'gm')->firstOrFail();
        $this->assertFalse($gm->hasPermissionTo('material.list'));
        $this->actingAs($gm)->get('/materials')->assertStatus(403);
    }

    public function test_read_only_material_permission_cannot_open_list_page(): void
    {
        DB::beginTransaction();
        try {
            $user = User::create([
                'username' => 'p203-readonly-' . uniqid(),
                'name' => 'P2-03 只读探针',
                'email' => uniqid() . '@example.test',
                'password' => bcrypt('secret'),
                'status' => 1,
            ]);
            $user->givePermissionTo('material.read');
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            $this->assertTrue($user->hasPermissionTo('material.read'));
            $this->actingAs($user)->get('/materials')->assertStatus(403);
        } finally {
            DB::rollBack();
        }
    }

    public function test_guard_check_reports_m8_cross_page_permissions(): void
    {
        $code = Artisan::call('erp:guard:check');
        $out = Artisan::output();
        $this->assertSame(0, $code);
        $this->assertStringContainsString('M8 权限', $out);
        $this->assertStringContainsString('应拆细', $out);
        // 拆分后的页面权限不应再跨 >3 页
        $this->assertStringNotContainsString('M8 权限 quality.inspection.view', $out);
        $this->assertStringNotContainsString('M8 权限 material.list', $out);
    }

    public function test_roles_keep_equivalent_grants_after_split(): void
    {
        // 原持有 quality.view 的岗位现在至少持有检验记录权限
        foreach (['role_qc_manager', 'role_qc_iqc', 'role_qc_ipqc', 'role_qc_fqc', 'role_wh_keeper', 'role_wh_receiver'] as $role) {
            $this->assertTrue(Role::where('name', $role)->firstOrFail()->hasPermissionTo('quality.inspection.view'),
                "{$role} 丢失检验记录查看权限");
        }

        // 原持有 material.view 的岗位现在至少持有业务读取权限
        foreach (['role_eng_hw', 'role_pur_specialist', 'role_wh_keeper', 'role_sal_specialist'] as $role) {
            $this->assertTrue(Role::where('name', $role)->firstOrFail()->hasPermissionTo('material.read'),
                "{$role} 丢失物料读取权限");
        }
    }
}
