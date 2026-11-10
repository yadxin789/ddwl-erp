<?php

namespace App\Models\Ecom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcomOrder extends Model
{
    protected $fillable = [
        'ecom_order_no', 'platform_id', 'platform_instance_id', 'buyer_nick',
        'receiver_name', 'receiver_phone', 'receiver_address', 'total_amount', 'pay_amount',
        'status', 'sync_status', 'raw_payload', 'tracking_no', 'last_error',
        'paid_at', 'shipped_at', 'pulled_at', 'pushed_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'pulled_at' => 'datetime',
        'pushed_at' => 'datetime',
        'raw_payload' => 'array',
        'total_amount' => 'decimal:2',
        'pay_amount' => 'decimal:2',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(EcomPlatform::class, 'platform_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlatformInstance::class, 'platform_instance_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcomOrderItem::class, 'ecom_order_id');
    }
}
