<?php

namespace App\Models\MasterData;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 标准成本版本（P1-02 / D-14）
 */
class MaterialStandardCost extends Model
{
    protected $fillable = [
        'material_id', 'version', 'standard_cost', 'currency', 'effective_from', 'effective_to',
        'status', 'source', 'approved_by', 'approved_at', 'remark',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
        'version' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeEffectiveAt($query, ?string $date = null)
    {
        $date = $date ?: now()->toDateString();

        return $query->where('effective_from', '<=', $date)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date));
    }

    public function label(): string
    {
        return match ($this->source) {
            'demo' => '演示数据（虚拟标准价，上线前由财务替换）',
            'fallback' => '临时兜底（最近采购价）',
            default => '财务维护',
        };
    }
}
