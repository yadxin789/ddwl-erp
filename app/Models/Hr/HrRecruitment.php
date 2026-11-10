<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrRecruitment extends Model
{
    protected $fillable = ['req_no', 'position', 'headcount', 'department', 'requirement', 'status'];
}
