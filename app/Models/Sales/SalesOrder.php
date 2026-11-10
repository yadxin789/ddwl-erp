<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasDataScope;
use App\Models\MasterData\Customer;
use App\Models\Organization\User;

class SalesOrder extends Model
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本订单销售员（S5） */
    protected static ?string $dataScopeOwnerColumn = 'salesperson_id';

    protected $fillable = [
        'order_no', 'customer_id', 'quote_id', 'order_date', 'delivery_date',
        'status', 'total_amount', 'shipped_qty', 'remark', 'salesperson_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'delivery_date' => 'date',
        'total_amount' => 'decimal:2',
        'shipped_qty' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class, 'order_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'sales_order_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class, 'quote_id');
    }
}
