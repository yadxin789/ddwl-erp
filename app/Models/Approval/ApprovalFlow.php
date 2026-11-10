<?php

namespace App\Models\Approval;

use Illuminate\Database\Eloquent\Model;

class ApprovalFlow extends Model
{
    public $timestamps = false;
    protected $fillable = ['flow_code', 'flow_name', 'business_type', 'steps_json', 'status'];
    protected $casts = ['steps_json' => 'array', 'status' => 'boolean'];
}
