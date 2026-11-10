<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;

class SensitiveAccessLog extends Model
{
    public $timestamps = false;
    protected $table = 'sensitive_access_logs';
    protected $fillable = ['user_id', 'action', 'target', 'ip_address', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
