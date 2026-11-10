<?php

namespace App\Models\Production;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationLog extends Model
{
    public $timestamps = false;
    protected $fillable = ['production_order_id', 'operation_name', 'worker_id', 'good_qty', 'defective_qty', 'defect_code', 'work_hours', 'reported_at', 'remark'];
    protected $casts = ['reported_at' => 'datetime'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
