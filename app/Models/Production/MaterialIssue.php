<?php

namespace App\Models\Production;

use App\Models\Inventory\Warehouse;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialIssue extends Model
{
    protected $fillable = ['issue_no', 'production_order_id', 'warehouse_id', 'status', 'issue_date', 'created_by'];
    protected $casts = ['issue_date' => 'date'];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class, 'material_issue_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
