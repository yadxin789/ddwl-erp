<?php

namespace App\Models\Finance;

use App\Models\Concerns\HasDataScope;
use App\Models\MasterData\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInvoice extends Model
{
    use HasDataScope;

    // P0-08：发票按开票责任人归属（owner_id），salesperson_id 记录业务员
    protected static ?string $dataScopeOwnerColumn = 'owner_id';

    protected $fillable = ['invoice_no', 'customer_id', 'sales_order_id', 'invoice_date', 'due_date', 'amount', 'tax_amount', 'paid_amount', 'status', 'owner_id', 'salesperson_id'];
    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
