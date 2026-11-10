<?php

namespace App\Models\Crm;

use App\Models\MasterData\Customer;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmOpportunity extends Model
{
    protected $fillable = ['op_no', 'name', 'lead_id', 'customer_id', 'expected_amount', 'probability', 'expected_close_date', 'stage', 'owner_id'];
    protected $casts = ['expected_close_date' => 'date', 'expected_amount' => 'decimal:2'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
