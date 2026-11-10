<?php

namespace App\Models\Production;

use App\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routing extends Model
{
    protected $fillable = ['routing_code', 'product_material_id', 'version', 'status'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'routing_id')->orderBy('operation_seq');
    }
}
