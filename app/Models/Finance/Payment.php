<?php

namespace App\Models\Finance;

use App\Models\MasterData\Customer;
use App\Models\MasterData\Supplier;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['payment_no', 'direction', 'customer_id', 'supplier_id', 'amount', 'method', 'status', 'paid_at', 'ref_no', 'user_id'];
    protected $casts = ['paid_at' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** P1-03：核销明细（一条收付款可拆给多张发票/账单） */
    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }
}
