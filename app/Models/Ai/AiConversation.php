<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = [
        'title', 'user_id', 'provider_id', 'conversation_type',
        'skill_code', 'message_count', 'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'message_count' => 'integer',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AiAttachment::class, 'conversation_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(AiSkill::class, 'skill_code', 'code');
    }
}
