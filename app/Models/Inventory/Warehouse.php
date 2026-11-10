<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'warehouse_code', 'name', 'type', 'address', 'status', 'department_id',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }
}
