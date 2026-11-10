<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'material_id', 'qty', 'shipped_qty', 'unit_price', 'std_cost_amount', 'remark',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'shipped_qty' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'std_cost_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'order_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
