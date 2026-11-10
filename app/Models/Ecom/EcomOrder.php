<?php

namespace App\Models\Ecom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcomOrder extends Model
{
    protected $fillable = ['ecom_order_no', 'platform_id', 'buyer_nick', 'receiver_name', 'receiver_phone', 'receiver_address', 'total_amount', 'pay_amount', 'status', 'paid_at', 'shipped_at'];
    protected $casts = ['paid_at' => 'datetime', 'shipped_at' => 'datetime'];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(EcomPlatform::class, 'platform_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcomOrderItem::class, 'ecom_order_id');
    }
}
