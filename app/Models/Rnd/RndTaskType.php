<?php

namespace App\Models\Rnd;

use App\Models\Organization\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndTaskType extends Model
{
    public $timestamps = false;
    protected $fillable = ['code', 'name', 'department_id', 'description', 'is_required', 'sort_order', 'status'];
    protected $casts = ['is_required' => 'boolean', 'status' => 'boolean'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
