<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P5 项目电话会议房间：信令走数据库轮询（rnd_meeting_signals），音频走 WebRTC 局域网 P2P，断网可用。
 */
class RndMeeting extends Model
{
    protected $table = 'rnd_meetings';

    protected $fillable = ['project_id', 'title', 'status', 'creator_id', 'started_at', 'ended_at'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(RndMeetingMember::class, 'meeting_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** 在线成员：最近 25 秒内有心跳 */
    public function onlineMembers()
    {
        return $this->members()
            ->with('user')
            ->where('last_seen_at', '>=', now()->subSeconds(25))
            ->get();
    }
}
