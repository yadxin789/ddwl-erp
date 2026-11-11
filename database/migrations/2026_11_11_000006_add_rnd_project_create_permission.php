<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 研发项目创建权独立：只有项目经理（研发经理）可以新建研发项目，
 * 同时给各部门主管补“研发任务审核”所需的最小权限。
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'rnd.project.create', 'guard_name' => 'web']);

        $grants = [
            'role_rnd_manager' => ['rnd.project.create', 'rnd.view', 'rnd.approve'],
            'role_project_creator' => ['rnd.project.create', 'rnd.view'],
            // 各部门经理可能被指派为研发任务审核人
            'role_prod_manager' => ['rnd.view', 'rnd.approve'],
            'role_qc_manager' => ['rnd.view', 'rnd.approve'],
            'role_pur_manager' => ['rnd.view', 'rnd.approve'],
            'role_sal_manager' => ['rnd.view', 'rnd.approve'],
            'role_wh_supervisor' => ['rnd.view', 'rnd.approve'],
            'role_adm_manager' => ['rnd.view', 'rnd.approve'],
        ];

        foreach ($grants as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            foreach ($permissions as $permission) {
                if (Permission::where('name', $permission)->where('guard_name', 'web')->exists()
                    && ! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['role_rnd_manager', 'role_project_creator'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role && $role->hasPermissionTo('rnd.project.create')) {
                $role->revokePermissionTo('rnd.project.create');
            }
        }

        Permission::where('name', 'rnd.project.create')->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
