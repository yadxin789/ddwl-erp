<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = ['coupon_no', 'name', 'amount', 'min_spend', 'total_qty', 'received_qty', 'expire_date', 'status'];
    protected $casts = ['expire_date' => 'date'];
}
