<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;

class AiProvider extends Model
{
    // 注意：api_key 为敏感凭据，不允许批量赋值
    protected $fillable = ['name', 'provider_code', 'api_base', 'model', 'status'];

    protected $hidden = ['api_key'];

    protected $casts = [
        'status' => 'boolean',
        'api_key' => 'encrypted',
    ];
}
