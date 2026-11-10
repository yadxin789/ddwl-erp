<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{
    protected $fillable = ['spu_id', 'sku_code', 'spec', 'price', 'stock'];
}
