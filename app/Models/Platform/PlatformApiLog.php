<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PlatformApiLog extends Model
{
    protected $fillable = ['platform', 'api_path', 'method', 'http_code', 'duration_ms', 'request_body', 'response_body', 'called_at'];
    protected $casts = ['called_at' => 'datetime'];
}
