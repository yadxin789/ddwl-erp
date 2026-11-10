<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrPerformance extends Model
{
    protected $fillable = ['employee_id', 'period', 'score', 'grade', 'comment'];

    public function employee()
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }
}
