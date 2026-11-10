<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class CustomerReturnLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'return_id', 'material_id', 'lot_no', 'qty', 'disposition', 'disposition_qty', 'handled_at',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'disposition_qty' => 'decimal:2',
        'handled_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
