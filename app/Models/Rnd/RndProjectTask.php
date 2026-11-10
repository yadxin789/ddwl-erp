<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RndProjectTask extends Model
{
    protected $fillable = ['project_id', 'task_type_id', 'department_id', 'executor_id', 'auditor_id', 'start_date', 'end_date', 'status', 'is_overdue', 'extension_days'];
    protected $casts = ['end_date' => 'date', 'start_date' => 'date', 'is_overdue' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function taskType(): BelongsTo
    {
        return $this->belongsTo(RndTaskType::class, 'task_type_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(RndProjectDocument::class, 'task_id');
    }
}
