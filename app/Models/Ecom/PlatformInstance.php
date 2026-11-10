<?php

namespace App\Models\Ecom;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P1-07 电商接入实例（配置层面，不含交易数据）
 */
class PlatformInstance extends Model
{
    protected $fillable = [
        'platform_key', 'name', 'region', 'credentials_json', 'mapping_json',
        'api_base_url', 'auth_type', 'callback_url', 'default_warehouse_id',
        'sync_frequency', 'status', 'last_check_at', 'last_check_result',
        'last_check_message', 'last_synced_at', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'credentials_json' => 'encrypted:array',
        'mapping_json' => 'array',
        'status' => 'boolean',
        'last_check_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'expires_at' => 'date',
    ];

    protected $hidden = ['credentials_json'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'default_warehouse_id');
    }

    public function scopeEnabled($query)
    {
        return $query->where('status', true);
    }

    /** 授权到期提醒：7 天内到期或已过期 */
    public function isExpiringSoon(): bool
    {
        return $this->expires_at && $this->expires_at->lte(now()->addDays(7));
    }

    public function statusLabel(): string
    {
        if (! $this->status) {
            return '已停用';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return '授权过期';
        }
        if ($this->last_check_result === 'failed') {
            return '自检失败';
        }
        if ($this->last_check_result === 'ok') {
            return '已启用';
        }

        return '待自检';
    }
}
