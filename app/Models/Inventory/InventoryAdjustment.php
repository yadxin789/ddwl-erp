<?php

namespace App\Models\Inventory;

use App\Models\MasterData\Material;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 库存调整单（P0-04）：小额自动通过、超阈值待仓库主管审批，库存只在生效时变动。
 */
class InventoryAdjustment extends Model
{
    protected $fillable = [
        'adjustment_no', 'source', 'stock_id', 'stocktake_id', 'stocktake_line_id',
        'material_id', 'lot_no', 'warehouse_id', 'location_id',
        'status', 'before_qty', 'after_qty', 'delta_qty',
        'unit_cost', 'amount', 'threshold', 'auto_approved',
        'reason', 'requested_by', 'approved_by', 'approved_at', 'approval_remark',
    ];

    protected $casts = [
        'before_qty' => 'decimal:4',
        'after_qty' => 'decimal:4',
        'delta_qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'amount' => 'decimal:2',
        'threshold' => 'decimal:2',
        'auto_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
