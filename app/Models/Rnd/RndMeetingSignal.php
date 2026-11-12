<?php

namespace App\Models\Rnd;

use Illuminate\Database\Eloquent\Model;

/**
 * 会议信令（WebRTC SDP / ICE candidate），按"发给谁"落库，参与者轮询领取。
 */
class RndMeetingSignal extends Model
{
    protected $table = 'rnd_meeting_signals';

    public $timestamps = false;

    protected $fillable = ['meeting_id', 'from_user_id', 'to_user_id', 'type', 'payload', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
