<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class PaymentTerm extends Model
{
    protected $fillable = ['code', 'name', 'days', 'description', 'status'];
    protected $casts = ['days' => 'integer', 'status' => 'boolean'];
}
