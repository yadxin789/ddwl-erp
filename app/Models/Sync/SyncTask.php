<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncTask extends Model
{
    protected $fillable = ['name', 'target', 'direction', 'data_type', 'cron_expr', 'status', 'last_run_at'];
    protected $casts = ['last_run_at' => 'datetime'];

    public function logs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'sync_task_id');
    }
}
