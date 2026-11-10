<?php

namespace App\Models\Rnd;

use Illuminate\Database\Eloquent\Model;

class RndTaskType extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'department_id', 'description', 'is_required', 'sort_order'];
    protected $casts = ['is_required' => 'boolean'];
}
