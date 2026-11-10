<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = ['sync_task_id', 'run_at', 'status', 'total_count', 'success_count', 'message'];
    protected $casts = ['run_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(SyncTask::class, 'sync_task_id');
    }
}
