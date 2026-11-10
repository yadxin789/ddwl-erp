<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** P3-2 APS 排程批次。 */
class ApsPlan extends Model
{
    protected $fillable = ['plan_no', 'horizon_start', 'horizon_end', 'status', 'summary_json', 'created_by'];

    protected $casts = [
        'horizon_start' => 'date',
        'horizon_end' => 'date',
        'summary_json' => 'array',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(ApsPlanEntry::class, 'plan_id');
    }
}
