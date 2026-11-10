<?php

namespace App\Models\MasterData;

use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasDataScope;

    // P0-08：供应商归采购所有（owner_id）+ 归属部门
    protected static ?string $dataScopeOwnerColumn = 'owner_id';
    protected static ?string $dataScopeDepartmentColumn = 'department_id';

    protected $fillable = [
        'supplier_code', 'supplier_name', 'contact_person', 'contact_phone',
        'email', 'address', 'supplier_type', 'payment_terms', 'lead_time_days', 'status',
        'owner_id', 'department_id',
    ];

    protected $casts = [
        'status' => 'integer',
        'lead_time_days' => 'integer',
    ];

    /** 仅启用供应商 */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function mpns(): HasMany
    {
        return $this->hasMany(MaterialMpn::class, 'supplier_id');
    }
}
