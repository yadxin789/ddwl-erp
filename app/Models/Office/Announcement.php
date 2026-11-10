<?php

namespace App\Models\Office;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = ['title', 'content', 'category', 'publisher_id', 'published_at', 'status'];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => 'integer',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
}
