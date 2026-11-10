<?php

namespace App\Models\Inventory;

use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryStock extends Model
{
    use HasDataScope;

    protected $table = 'inventory_stock';

    // P0-08：库存没有人员归属列，按"本部门仓"过滤（仓管只见仓储部名下仓库）
    protected static ?string $dataScopeWarehouseColumn = 'warehouse_id';

    protected $fillable = [
        'material_id', 'lot_no', 'warehouse_id', 'location_id', 'status',
        'qty', 'reserved_qty', 'receive_date', 'supplier_batch',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'reserved_qty' => 'decimal:2',
        'receive_date' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'location_id');
    }
}
