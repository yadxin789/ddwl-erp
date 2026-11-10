<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class WarehouseLocation extends Model
{
    protected $fillable = [
        'warehouse_id', 'location_code', 'zone', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
