<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrAttendance extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'check_in', 'check_out', 'status'];
    protected $casts = ['work_date' => 'date', 'check_in' => 'datetime', 'check_out' => 'datetime'];
}
