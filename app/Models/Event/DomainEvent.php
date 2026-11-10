<?php

namespace App\Models\Event;

use Illuminate\Database\Eloquent\Model;

class DomainEvent extends Model
{
    protected $fillable = ['name', 'agg_type', 'agg_id', 'data'];
    protected $casts = ['data' => 'array'];
}
