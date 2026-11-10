<?php

namespace App\Models\Bom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BomImport extends Model
{
    protected $fillable = [
        'import_no', 'file_name', 'file_path', 'product_material_id',
        'total_rows', 'success_rows', 'error_rows', 'status', 'created_by',
    ];

    public function rows(): HasMany
    {
        return $this->hasMany(BomImportRow::class, 'import_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }
}
