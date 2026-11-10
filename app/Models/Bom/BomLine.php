<?php

namespace App\Models\Bom;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BomLine extends Model
{
    protected $fillable = [
        'bom_header_id', 'parent_material_id', 'material_id', 'qty_per',
        'unit', 'ref_des', 'loss_rate', 'is_alternative', 'sort_order',
    ];

    protected $casts = [
        'qty_per' => 'decimal:4',
        'loss_rate' => 'decimal:2',
        'is_alternative' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bomHeader(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class, 'bom_header_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
