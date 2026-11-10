<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;

class LoginFailure extends Model
{
    protected $table = 'login_failures';
    protected $fillable = ['username', 'ip_address', 'fail_count', 'locked_until'];
    protected $casts = ['locked_until' => 'datetime'];
}
