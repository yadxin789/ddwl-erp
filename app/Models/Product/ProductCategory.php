<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    protected $fillable = ['code', 'name', 'parent_id', 'sort'];
}
