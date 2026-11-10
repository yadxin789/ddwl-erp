<?php

namespace App\Models\Purchasing;

use App\Models\Concerns\HasDataScope;
use App\Models\MasterData\Supplier;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本单采购员（S5） */
    protected static ?string $dataScopeOwnerColumn = 'buyer_id';

    protected $fillable = [
        'po_no', 'supplier_id', 'buyer_id', 'order_date', 'expected_date',
        'status', 'total_amount', 'received_qty', 'remark',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'total_amount' => 'decimal:2',
        'received_qty' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'po_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class, 'po_id');
    }
}
