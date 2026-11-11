<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = [
        'rule_code', 'prefix', 'date_format', 'seq_length', 'current_seq', 'status', 'description',
    ];

    protected $casts = [
        'seq_length' => 'integer',
        'current_seq' => 'integer',
        'status' => 'boolean',
    ];
}
