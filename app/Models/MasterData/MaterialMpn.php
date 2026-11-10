<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MaterialMpn extends Model
{
    protected $fillable = [
        'material_id', 'mpn', 'manufacturer', 'supplier_id', 'is_primary',
        'is_alternative', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'is_primary' => 'integer',
        'is_alternative' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
