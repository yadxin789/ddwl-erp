<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Model;

class IntegrationOutbox extends Model
{
    protected $fillable = [
        'event_type', 'ref_type', 'ref_id', 'payload', 'status', 'retry_count',
        'published_at', 'next_retry_at', 'last_error', 'dead_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'dead_at' => 'datetime',
    ];

    protected $table = 'integration_outbox';
}
