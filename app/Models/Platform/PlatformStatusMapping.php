<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;

class PlatformStatusMapping extends Model
{
    protected $fillable = ['platform', 'platform_status', 'local_status'];
}
