<?php

namespace App\Models\Crm;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    protected $fillable = ['lead_no', 'company_name', 'contact_name', 'phone', 'email', 'source', 'status', 'owner_id', 'remark'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(CrmFollowUp::class, 'lead_id');
    }
}
