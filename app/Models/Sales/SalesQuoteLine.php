<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalesQuoteLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'quote_id', 'material_id', 'qty', 'unit_price', 'delivery_days', 'remark',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'delivery_days' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
