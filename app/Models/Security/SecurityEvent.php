<?php

namespace App\Models\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEvent extends Model
{
    public $timestamps = false;
    protected $table = 'security_events';
    protected $fillable = ['event_type', 'user_id', 'ip_address', 'detail', 'level', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Organization\User::class, 'user_id');
    }
}
