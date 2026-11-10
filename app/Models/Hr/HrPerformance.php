<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrPerformance extends Model
{
    protected $fillable = ['employee_id', 'period', 'score', 'grade', 'comment'];
}
