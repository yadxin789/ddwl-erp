<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'name', 'rate', 'is_base'];
}
