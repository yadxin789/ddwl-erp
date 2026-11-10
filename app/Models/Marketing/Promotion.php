<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = ['code', 'name', 'type', 'value', 'start_date', 'end_date', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];
}
