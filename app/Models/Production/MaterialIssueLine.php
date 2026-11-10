<?php

namespace App\Models\Production;

use App\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialIssueLine extends Model
{
    public $timestamps = false;
    protected $fillable = ['material_issue_id', 'material_id', 'lot_no', 'qty'];
    protected $casts = ['qty' => 'decimal:2'];

    public function materialIssue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
