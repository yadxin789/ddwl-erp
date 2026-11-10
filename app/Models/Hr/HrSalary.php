<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrSalary extends Model
{
    protected $fillable = ['employee_id', 'period', 'base', 'bonus', 'deduction', 'net'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
