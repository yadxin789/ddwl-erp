<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxEntity extends Model
{
    protected $fillable = ['code', 'name', 'type', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class, 'tax_entity_id');
    }
}
