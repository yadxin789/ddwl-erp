<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    public $timestamps = false;
    protected $table = 'login_logs';
    protected $fillable = ['user_id', 'username', 'ip_address', 'user_agent', 'status', 'fail_reason', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];
}
