<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrSalary extends Model
{
    protected $fillable = ['employee_id', 'period', 'base', 'bonus', 'deduction', 'net'];
}
