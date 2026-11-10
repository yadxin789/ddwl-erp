<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 收付款核销明细（P1-03 / D-04）
 * 一条收款可拆给多张发票，一条付款可拆给多张供应商账单。
 */
class PaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'invoice_id', 'bill_id', 'amount', 'remark'];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'invoice_id')->withoutGlobalScopes();
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'bill_id')->withoutGlobalScopes();
    }
}
