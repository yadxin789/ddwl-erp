<?php

namespace App\Models\Finance;

use App\Models\Concerns\HasDataScope;
use App\Models\MasterData\Supplier;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBill extends Model
{
    use HasDataScope;

    // P0-08：应付账单按采购责任人归属（owner_id），buyer_id 记录业务员
    protected static ?string $dataScopeOwnerColumn = 'owner_id';

    protected $fillable = ['bill_no', 'supplier_id', 'purchase_order_id', 'bill_date', 'due_date', 'amount', 'paid_amount', 'status', 'owner_id', 'buyer_id'];
    protected $casts = ['bill_date' => 'date', 'due_date' => 'date'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
