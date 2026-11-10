<?php

namespace App\Models\Production;

use App\Models\Inventory\Warehouse;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    protected $fillable = ['stocktake_no', 'warehouse_id', 'status', 'plan_date', 'finish_date', 'created_by'];
    protected $casts = ['plan_date' => 'date', 'finish_date' => 'datetime'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StocktakeLine::class, 'stocktake_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
