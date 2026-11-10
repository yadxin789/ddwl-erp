<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Material extends Model
{
    protected $fillable = [
        'material_code', 'category_code', 'subcategory_code', 'name', 'spec',
        'package', 'unit', 'rohs_status', 'cost_price', 'status', 'remark',
    ];

    protected $casts = [
        'rohs_status' => 'integer',
        'status' => 'integer',
        'cost_price' => 'decimal:4',
    ];

    /** 仅启用物料 */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function mpns(): HasMany
    {
        return $this->hasMany(MaterialMpn::class, 'material_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_code', 'category_code');
    }
}
