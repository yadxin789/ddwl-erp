<?php

namespace App\Models\Concerns;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * 软删除审计：进入回收站时记录删除人，便于个人回收站按 deleted_by 展示和筛选。
 */
trait RecordsDeletedBy
{
    public static function bootRecordsDeletedBy(): void
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
                return;
            }

            if ($model->getAttribute('deleted_by') === null && Auth::id()) {
                $model->setAttribute('deleted_by', Auth::id());
                $model->saveQuietly();
            }
        });
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
