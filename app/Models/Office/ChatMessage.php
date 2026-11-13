<?php

namespace App\Models\Office;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use SoftDeletes;

    public $timestamps = false;
    protected $table = 'system_chat_messages';
    protected $fillable = ['room_id', 'sender_id', 'msg_type', 'content', 'file_id', 'created_at'];
    protected $casts = ['created_at' => 'datetime'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
