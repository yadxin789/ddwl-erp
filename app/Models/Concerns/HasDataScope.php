<?php

namespace App\Models\Concerns;

use App\Support\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 行级数据范围（S5）
 *
 * 用法：模型 use HasDataScope，并声明归属列：
 *   protected static ?string $dataScopeOwnerColumn = 'salesperson_id';
 *   protected static ?string $dataScopeDepartmentColumn = 'department_id';
 *   protected static ?string $dataScopeWarehouseColumn = 'warehouse_id';   // 无人员归属列时按"本部门仓"过滤
 *
 * 语义：
 *  - all        → 不加任何条件
 *  - department → department 列 in 本人部门；无部门/无列则退化为 owner；都没有 → 0 行
 *  - self       → owner 列 = 本人；无 owner 列 → 0 行
 *  - none       → 业务数据一律 0 行（系统管理员不参与业务）
 *
 * P0-08：**默认 fail-closed**——声明了范围语义却找不到可用归属列时返回 0 行，
 * 而不是像以前那样静默放行全表。确属公共主数据的模型显式声明
 * `protected static bool $dataScopeUnscoped = true;` 并在计划文档登记。
 */
trait HasDataScope
{
    protected static function bootHasDataScope(): void
    {
        static::addGlobalScope('data_scope', function (Builder $builder) {
            if (! auth()->check()) {
                return;
            }

            $model = $builder->getModel();

            if ($model::$dataScopeUnscoped ?? false) {
                return;
            }

            /** @var DataScopeService $service */
            $service = app(DataScopeService::class);
            $scope = $service->forCurrentUser();
            $userId = auth()->id();

            $ownerColumn = $model::$dataScopeOwnerColumn ?? null;
            $deptColumn = $model::$dataScopeDepartmentColumn ?? null;
            $warehouseColumn = $model::$dataScopeWarehouseColumn ?? null;

            // 库存按"本部门仓"过滤：warehouse_id 属于本人部门名下仓库；无部门/无仓库 → 0 行
            $applyWarehouseScope = function (?int $departmentId) use ($builder, $model, $warehouseColumn): void {
                if (! $departmentId) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $warehouseIds = DB::table('warehouses')
                    ->where('department_id', $departmentId)
                    ->pluck('id')
                    ->all();

                if (! $warehouseIds) {
                    $builder->whereRaw('1 = 0');

                    return;
                }

                $builder->whereIn($model->qualifyColumn($warehouseColumn), $warehouseIds);
            };

            switch ($scope) {
                case DataScopeService::ALL:
                    return;

                case DataScopeService::NONE:
                    $builder->whereRaw('1 = 0');

                    return;

                case DataScopeService::DEPARTMENT:
                    $deptId = $service->departmentId();

                    if ($deptColumn && $deptId) {
                        $builder->where($model->qualifyColumn($deptColumn), $deptId);

                        return;
                    }
                    if ($ownerColumn) {
                        $builder->where($model->qualifyColumn($ownerColumn), $userId);

                        return;
                    }
                    if ($warehouseColumn) {
                        $applyWarehouseScope($deptId);

                        return;
                    }

                    // fail-closed：声明了范围但表没有可用归属列
                    $builder->whereRaw('1 = 0');

                    return;

                case DataScopeService::SELF:
                default:
                    if ($ownerColumn) {
                        $builder->where($model->qualifyColumn($ownerColumn), $userId);

                        return;
                    }
                    if ($warehouseColumn) {
                        $applyWarehouseScope($service->departmentId());

                        return;
                    }

                    // fail-closed：无归属列不再放行全表
                    $builder->whereRaw('1 = 0');

                    return;
            }
        });
    }
}
