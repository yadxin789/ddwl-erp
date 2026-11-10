<?php

namespace App\Models\Ecom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** P3-08 电商订单对账批次：订单头金额 vs 订单行金额。 */
class EcomReconciliationRun extends Model
{
    protected $fillable = [
        'platform_instance_id', 'period_start', 'period_end', 'order_count', 'paid_count',
        'shipped_count', 'header_amount', 'item_amount', 'mismatch_count', 'result',
        'summary_json', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'header_amount' => 'decimal:2',
        'item_amount' => 'decimal:2',
        'order_count' => 'integer',
        'paid_count' => 'integer',
        'shipped_count' => 'integer',
        'mismatch_count' => 'integer',
        'summary_json' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(PlatformInstance::class, 'platform_instance_id');
    }
}
