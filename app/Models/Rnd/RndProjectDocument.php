<?php

namespace App\Models\Rnd;

use App\Models\Concerns\HasDataScope;
use App\Models\Organization\User;
use App\Models\System\MediaFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RndProjectDocument extends Model
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本图档上传人（S5）；研发经理为 all */
    protected static ?string $dataScopeOwnerColumn = 'uploader_id';

    public $timestamps = false;
    protected $fillable = ['project_id', 'task_id', 'version_no', 'file_id', 'uploader_id', 'audit_status', 'auditor_id', 'audit_at', 'audit_remark', 'is_baseline', 'baseline_at', 'superseded_by'];
    protected $casts = ['audit_at' => 'datetime', 'baseline_at' => 'datetime', 'is_baseline' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(RndProjectTask::class, 'task_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'file_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * P0-05：路由绑定绕开行级范围（审核人不是上传人，按 uploader_id 过滤会 404），
     * 访问控制交给 RndProjectDocumentPolicy。
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::withoutGlobalScopes()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }
}
