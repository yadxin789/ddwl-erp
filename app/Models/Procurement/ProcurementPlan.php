<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class ProcurementPlan extends Model
{
    protected $fillable = ['plan_no', 'title', 'plan_date', 'total_amount', 'status'];
    protected $casts = ['plan_date' => 'date'];
}
