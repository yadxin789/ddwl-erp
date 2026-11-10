<?php

namespace App\Models\Concerns;

use App\Support\TenancyContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * P3-4 按租户过滤 + 新建时自动继承当前租户。
 * 只有上下文非空才加条件，平台账号（空）不受影响。
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(TenancyContext::class)->id();
            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            $tenantId = app(TenancyContext::class)->id();
            if ($tenantId !== null && ! $model->tenant_id) {
                $model->tenant_id = $tenantId;
            }
        });
    }
}
