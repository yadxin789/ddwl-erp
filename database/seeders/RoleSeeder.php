<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色 Seeder：系统管理员 / 仓库主管 / 仓管 / 采购专员 / 销售专员 / 工程师 / 品质主管
 * 每个角色按职责授予对应模块权限
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = [
            'system_admin' => [
                'label' => '系统管理员',
                // P0-08：系统管理员只保留运维类权限（system.* / admin.* / ai.*），
                // 不再"顺手拿全部业务权限"——业务数据对 SYS-ADMIN 是 none 范围，授权与范围必须一致
                'permissions' => array_values(array_filter(
                    Permission::pluck('name')->all(),
                    fn (string $name) => str_starts_with($name, 'system.')
                        || str_starts_with($name, 'admin.')
                        || str_starts_with($name, 'ai.')
                )),
            ],
            'warehouse_supervisor' => [
                'label' => '仓库主管',
                'permissions' => [
                    'warehouse.view', 'warehouse.create', 'warehouse.edit',
                    'inventory.view', 'inventory.operate', 'inventory.adjust', 'inventory.audit', 'inventory.transaction.view',
                    'material.list', 'material.read',
                    'bom.view',
                    'quality.inspection.view', 'quality.template.view', 'quality.defect.view',
                    'purchase.view', 'purchase.receive',
                    'sales.ship',
                ],
            ],
            'warehouse_keeper' => [
                'label' => '仓管',
                'permissions' => [
                    'warehouse.view',
                    'inventory.view', 'inventory.operate', 'inventory.transaction.view',
                    'material.list', 'material.read',
                    'bom.view',
                    'purchase.view', 'purchase.receive',
                    'sales.ship',
                ],
            ],
            'purchase_specialist' => [
                'label' => '采购专员',
                'permissions' => [
                    'purchase.view', 'purchase.create', 'purchase.edit', 'purchase.receive', 'purchase.return',
                    'supplier.view', 'supplier.create', 'supplier.edit',
                    'material.list', 'material.read', 'material.mpn.view',
                    'inventory.view', 'inventory.transaction.view',
                    'report.supplier',
                ],
            ],
            'sales_specialist' => [
                'label' => '销售专员',
                'permissions' => [
                    'sales.view', 'sales.create', 'sales.edit', 'sales.ship', 'sales.return',
                    'customer.view', 'customer.create', 'customer.edit',
                    'material.list', 'material.read',
                    'inventory.view', 'inventory.transaction.view',
                    'report.sales',
                ],
            ],
            'engineer' => [
                'label' => '工程师',
                'permissions' => [
                    'material.list', 'material.read', 'material.create', 'material.edit', 'material.import',
                    'material.mpn.view', 'material.mpn.create', 'material.mpn.edit',
                    'bom.view', 'bom.create', 'bom.edit', 'bom.import',
                    'inventory.view',
                ],
            ],
            'quality_supervisor' => [
                'label' => '品质主管',
                'permissions' => [
                    'quality.inspection.view', 'quality.template.view', 'quality.defect.view', 'quality.create', 'quality.edit', 'quality.approve',
                    'inventory.view', 'inventory.operate', 'inventory.transaction.view',
                    'material.list', 'material.read',
                    'bom.view',
                    'purchase.view', 'purchase.receive',
                    'report.quality',
                ],
            ],
        ];

        foreach ($roles as $code => $config) {
            $role = Role::firstOrCreate(['name' => $code, 'guard_name' => 'web']);
            $role->syncPermissions($config['permissions']);
        }
    }
}
