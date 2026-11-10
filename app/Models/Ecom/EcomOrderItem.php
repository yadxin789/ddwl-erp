<?php

namespace App\Models\Ecom;

use Illuminate\Database\Eloquent\Model;

class EcomOrderItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['ecom_order_id', 'outer_sku', 'product_name', 'qty', 'price'];
}
