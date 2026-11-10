<?php

namespace App\Models\MasterData;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToTenant, HasDataScope;

    // P0-08：客户归销售所有（owner_id）+ 归属部门
    protected static ?string $dataScopeOwnerColumn = 'owner_id';
    protected static ?string $dataScopeDepartmentColumn = 'department_id';

    protected $fillable = [
        'customer_code', 'customer_name', 'contact_person', 'contact_phone',
        'email', 'address', 'tax_number', 'payment_terms', 'credit_limit', 'status',
        'owner_id', 'department_id', 'tenant_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'credit_limit' => 'decimal:2',
    ];

    /** 仅启用客户 */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
