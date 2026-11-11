<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiSkill extends Model
{
    protected $table = 'ai_skills';

    protected $fillable = ['code', 'name', 'description', 'type', 'config', 'enabled'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];
}
