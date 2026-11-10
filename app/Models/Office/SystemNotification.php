<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    public $timestamps = false;
    protected $table = 'system_notifications';
    protected $fillable = ['user_id', 'notify_type', 'title', 'content', 'is_read', 'business_type', 'business_id', 'created_at'];
    protected $casts = ['is_read' => 'boolean', 'created_at' => 'datetime'];
}
