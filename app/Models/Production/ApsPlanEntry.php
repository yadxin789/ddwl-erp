<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** P3-2 APS 日负荷明细：一工单一道工序在某天的占用工时。 */
class ApsPlanEntry extends Model
{
    protected $fillable = ['plan_id', 'production_order_id', 'operation_seq', 'operation_name', 'work_center_id', 'work_date', 'hours'];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ApsPlan::class, 'plan_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }
}
