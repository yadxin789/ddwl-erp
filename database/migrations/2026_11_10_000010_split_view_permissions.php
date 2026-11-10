<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * P2-03 权限颗粒度拆细：
 *  - quality.view  → quality.inspection.view / quality.template.view / quality.defect.view
 *  - material.view → material.list（物料页）/ material.read（业务读取引用）
 *
 * 迁移期先给持有旧权限的角色补授等价新权限，再删旧权限，保证行为不丢；
 * 回滚时反向恢复旧权限并删新权限。
 */
return new class extends Migration
{
    private array $split = [
        'quality.view' => ['quality.inspection.view', 'quality.template.view', 'quality.defect.view'],
        'material.view' => ['material.list', 'material.read'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleTable = config('permission.table_names.roles', 'roles');
        $permissionTable = config('permission.table_names.permissions', 'permissions');
        $rolePermissionTable = config('permission.table_names.role_permissions', 'role_has_permissions');

        foreach ($this->split as $old => $newOnes) {
            $oldId = DB::table($permissionTable)->where('name', $old)->value('id');

            $roleIds = $oldId ? DB::table($rolePermissionTable)->where('permission_id', $oldId)->pluck('role_id')->all() : [];

            foreach ($newOnes as $newName) {
                $perm = Permission::firstOrCreate(['name' => $newName, 'guard_name' => 'web']);

                foreach ($roleIds as $roleId) {
                    $roleName = DB::table($roleTable)->where('id', $roleId)->value('name');
                    if (! $roleName) {
                        continue;
                    }
                    $exists = DB::table($rolePermissionTable)
                        ->where('permission_id', $perm->id)
                        ->where('role_id', $roleId)
                        ->exists();
                    if (! $exists) {
                        DB::table($rolePermissionTable)->insert([
                            'permission_id' => $perm->id,
                            'role_id' => $roleId,
                        ]);
                    }
                }
            }

            if ($oldId) {
                DB::table($rolePermissionTable)->where('permission_id', $oldId)->delete();
                DB::table($permissionTable)->where('id', $oldId)->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleTable = config('permission.table_names.roles', 'roles');
        $permissionTable = config('permission.table_names.permissions', 'permissions');
        $rolePermissionTable = config('permission.table_names.role_permissions', 'role_has_permissions');

        foreach ($this->split as $old => $newOnes) {
            $oldPerm = Permission::firstOrCreate(['name' => $old, 'guard_name' => 'web']);
            $roleIds = [];

            foreach ($newOnes as $newName) {
                $newId = DB::table($permissionTable)->where('name', $newName)->value('id');
                if ($newId) {
                    $roleIds = array_merge($roleIds, DB::table($rolePermissionTable)->where('permission_id', $newId)->pluck('role_id')->all());
                }
            }

            foreach (array_unique($roleIds) as $roleId) {
                if (! DB::table($rolePermissionTable)->where('permission_id', $oldPerm->id)->where('role_id', $roleId)->exists()) {
                    DB::table($rolePermissionTable)->insert(['permission_id' => $oldPerm->id, 'role_id' => $roleId]);
                }
            }

            foreach ($newOnes as $newName) {
                $newId = DB::table($permissionTable)->where('name', $newName)->value('id');
                if ($newId) {
                    DB::table($rolePermissionTable)->where('permission_id', $newId)->delete();
                    DB::table($permissionTable)->where('id', $newId)->delete();
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
