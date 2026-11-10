<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    protected $fillable = [
        'category_code', 'category_name', 'description', 'sort_order', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    public function subcategories(): HasMany
    {
        return $this->hasMany(MaterialSubcategory::class, 'category_code', 'category_code');
    }
}
