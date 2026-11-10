<?php

namespace App\Models\Production;

use App\Models\Concerns\HasDataScope;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 领料申请（G9）：生产组长按数量申请，超 BOM 标准用量需生产主管审批（v1.3 §4.3 / D-13）。
 */
class MaterialIssueRequest extends Model
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本申请申请人（S5）；生产主管为 all，仍可审批 */
    protected static ?string $dataScopeOwnerColumn = 'requested_by';

    protected $fillable = [
        'request_no', 'production_order_id', 'purpose', 'status', 'has_over_bom',
        'requested_by', 'approver_id', 'approved_at', 'reject_reason',
    ];

    protected $casts = [
        'has_over_bom' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueRequestLine::class, 'request_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
