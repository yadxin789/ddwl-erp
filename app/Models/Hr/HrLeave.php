<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrLeave extends Model
{
    protected $fillable = ['leave_no', 'employee_id', 'type', 'from_date', 'to_date', 'days', 'reason', 'status'];
    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];
}
