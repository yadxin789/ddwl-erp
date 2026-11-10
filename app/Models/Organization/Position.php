<?php

namespace App\Models\Organization;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Position extends Model
{
    protected $fillable = [
        'pos_code', 'pos_name', 'department_id', 'role_names', 'data_scope', 'data_scope_type', 'description', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'role_names' => 'array',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }
}
