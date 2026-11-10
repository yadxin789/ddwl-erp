<?php

namespace App\Models\Procurement;

use App\Models\MasterData\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementArrival extends Model
{
    protected $fillable = ['arrival_no', 'plan_id', 'supplier_id', 'arrival_date', 'status'];
    protected $casts = ['arrival_date' => 'date'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProcurementPlan::class, 'plan_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
