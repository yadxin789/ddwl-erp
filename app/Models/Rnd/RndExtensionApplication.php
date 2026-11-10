<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndExtensionApplication extends Model
{
    public $timestamps = false;
    protected $fillable = ['task_id', 'applicant_id', 'apply_days', 'reason', 'status', 'approver_id', 'approved_at'];
    protected $casts = ['approved_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(RndProjectTask::class, 'task_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }
}
