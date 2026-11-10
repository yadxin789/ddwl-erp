<?php

namespace App\Models\MasterData;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MaterialSubcategory extends Model
{
    protected $fillable = [
        'category_code', 'subcategory_code', 'subcategory_name', 'description',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_code', 'category_code');
    }
}
