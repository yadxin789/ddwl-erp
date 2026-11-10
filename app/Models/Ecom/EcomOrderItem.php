<?php

namespace App\Models\Ecom;

use Illuminate\Database\Eloquent\Model;

class EcomOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['ecom_order_id', 'outer_sku', 'product_name', 'material_id', 'sku_mapped', 'qty', 'price'];

    protected $casts = [
        'sku_mapped' => 'boolean',
        'qty' => 'integer',
        'price' => 'decimal:2',
    ];
}
