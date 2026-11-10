<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrEmployee extends Model
{
    protected $fillable = ['emp_no', 'name', 'gender', 'birth_date', 'phone', 'department_id', 'position', 'hire_date', 'status'];
    protected $casts = ['birth_date' => 'date', 'hire_date' => 'date'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrDepartment::class, 'department_id');
    }
}
