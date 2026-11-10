<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $fillable = ['conversation_id', 'role', 'content', 'token_usage'];
}
