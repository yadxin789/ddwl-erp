<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'unit_code', 'unit_name', 'conversion_factor', 'status',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:4',
        'status' => 'boolean',
    ];
}
