<?php

namespace App\Models\Quality;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectiveAction extends Model
{
    protected $fillable = ['ca_no', 'inspection_record_id', 'problem', 'root_cause', 'action', 'owner_id', 'due_date', 'status'];
    protected $casts = ['due_date' => 'date'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function inspectionRecord(): BelongsTo
    {
        return $this->belongsTo(InspectionRecord::class, 'inspection_record_id');
    }
}
