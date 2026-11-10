<?php

namespace App\Models\Bom;

use Illuminate\Database\Eloquent\Model;
use App\Models\MasterData\Material;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
class BomImportRow extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'import_id', 'row_no', 'raw_mpn', 'raw_name', 'raw_spec', 'raw_qty',
        'raw_ref_des', 'raw_manufacturer', 'matched_material_id', 'match_status',
        'action', 'new_material_request_id', 'error_message',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(BomImport::class, 'import_id');
    }

    public function matchedMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'matched_material_id');
    }
}
