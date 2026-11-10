<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = ['title', 'user_id', 'provider_id', 'conversation_type'];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }
}
