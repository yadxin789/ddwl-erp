<?php

namespace App\Models\Purchasing;

use App\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'po_id', 'material_id', 'qty', 'received_qty', 'unit_price', 'remark',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'unit_price' => 'decimal:4',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
