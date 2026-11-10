<?php

namespace App\Models\Rnd;

use App\Models\Concerns\HasDataScope;
use App\Models\Bom\BomHeader;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P1-05 简版 ECR/ECO：变更单 → 审批（研发经理）→ 生成新 BOM 版本。
 */
class EcrChangeOrder extends Model
{
    use HasDataScope;

    protected static ?string $dataScopeOwnerColumn = 'applicant_id';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'change_no', 'project_id', 'bom_id', 'change_type', 'title', 'reason',
        'applicant_id', 'status', 'approver_id', 'approved_at', 'new_bom_id',
    ];

    protected $casts = ['approved_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class, 'bom_id');
    }

    public function newBom(): BelongsTo
    {
        return $this->belongsTo(BomHeader::class, 'new_bom_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
