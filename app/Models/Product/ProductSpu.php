<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpu extends Model
{
    protected $fillable = ['spu_no', 'name', 'category_id', 'brand_id', 'unit', 'market_price', 'sale_price', 'status', 'description'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'brand_id');
    }

    public function skus(): HasMany
    {
        return $this->hasMany(ProductSku::class, 'spu_id');
    }
}
