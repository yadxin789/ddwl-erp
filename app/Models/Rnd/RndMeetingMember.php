<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndMeetingMember extends Model
{
    protected $table = 'rnd_meeting_members';

    public $timestamps = false;

    protected $fillable = ['meeting_id', 'user_id', 'peer_id', 'joined_at', 'last_seen_at'];

    protected $casts = ['joined_at' => 'datetime', 'last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
