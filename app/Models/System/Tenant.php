<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['code', 'name', 'domain', 'contact_name', 'contact_phone', 'expired_at', 'status', 'settings'];
    protected $casts = ['expired_at' => 'date', 'settings' => 'array'];
}
