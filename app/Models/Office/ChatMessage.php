<?php

namespace App\Models\Office;

use App\Models\Concerns\RecordsDeletedBy;
use App\Models\Organization\User;
use App\Models\System\MediaFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use SoftDeletes;
    use RecordsDeletedBy;

    public $timestamps = false;
    protected $table = 'system_chat_messages';
    protected $fillable = ['room_id', 'sender_id', 'msg_type', 'content', 'file_id', 'created_at', 'deleted_by'];
    protected $casts = ['created_at' => 'datetime'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_id');
    }
}
