<?php

namespace App\Models\Quality;

use Illuminate\Database\Eloquent\Model;

class InspectionTemplate extends Model
{
    protected $fillable = ['code', 'name', 'stage', 'check_items', 'standard', 'status'];
    protected $casts = ['check_items' => 'array', 'status' => 'boolean'];
}
