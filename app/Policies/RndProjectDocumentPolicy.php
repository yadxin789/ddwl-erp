<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProjectDocument;

/**
 * P0-05 / D-01：交付物授权
 * audit：该任务指定的审核人或研发经理；view：项目参与者（上传人/执行人/审核人/创建人）。
 */
class RndProjectDocumentPolicy
{
    public function view(User $user, RndProjectDocument $document): bool
    {
        return $this->audit($user, $document);
    }

    public function audit(User $user, RndProjectDocument $document): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        $task = $document->task;
        if (! $task) {
            return false;
        }

        if ((int) $task->auditor_id === (int) $user->id) {
            return true;
        }
        if ((int) $task->executor_id === (int) $user->id) {
            return true;
        }

        return (int) ($document->project?->creator_id ?? 0) === (int) $user->id
            || (int) $document->uploader_id === (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasPermissionTo('rnd.approve');
    }
}
