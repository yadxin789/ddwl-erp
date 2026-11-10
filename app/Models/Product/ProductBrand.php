<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductBrand extends Model
{
    protected $fillable = ['code', 'name', 'logo'];
}
