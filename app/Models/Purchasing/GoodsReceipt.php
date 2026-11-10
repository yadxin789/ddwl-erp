<?php

namespace App\Models\Purchasing;

use App\Models\Concerns\HasDataScope;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use HasDataScope;

    // P0-08：收货单归收货员 + 仓储部
    protected static ?string $dataScopeOwnerColumn = 'receiver_id';
    protected static ?string $dataScopeDepartmentColumn = 'department_id';

    protected $fillable = [
        'receipt_no', 'po_id', 'supplier_id', 'receipt_date',
        'warehouse_id', 'status', 'total_qty',
        'receiver_id', 'department_id',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'total_qty' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class, 'receipt_id');
    }
}
