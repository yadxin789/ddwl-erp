<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class WecomMessageLog extends Model
{
    protected $fillable = ['msg_id', 'to_user', 'msg_type', 'content', 'status', 'error_msg', 'sent_at'];
    protected $casts = ['sent_at' => 'datetime'];
}
