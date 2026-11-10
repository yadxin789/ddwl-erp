<?php

namespace App\Models\Crm;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUp extends Model
{
    protected $fillable = ['lead_id', 'opportunity_id', 'user_id', 'type', 'content', 'followed_at'];
    protected $casts = ['followed_at' => 'datetime'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
