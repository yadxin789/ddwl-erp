<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProjectDocument;

/**
 * P0-05 / D-01：交付物授权
 * audit：只允许该任务指定的审核人本人（部门经理），且不能审核自己提交的文件；
 * view：项目参与者（上传人/执行人/审核人/创建人/研发经理）。
 */
class RndProjectDocumentPolicy
{
    public function view(User $user, RndProjectDocument $document): bool
    {
        if ($this->isManager($user) || (int) $document->uploader_id === (int) $user->id) {
            return true;
        }

        $task = $document->task;
        if (! $task) {
            return (int) ($document->project?->creator_id ?? 0) === (int) $user->id;
        }

        return (int) $task->executor_id === (int) $user->id
            || (int) $task->auditor_id === (int) $user->id
            || (int) ($document->project?->creator_id ?? 0) === (int) $user->id;
    }

    public function audit(User $user, RndProjectDocument $document): bool
    {
        $task = $document->task;
        if (! $task) {
            return false;
        }

        $auditorId = (int) ($task->auditor_id ?? 0);
        if ($auditorId === 0 || $auditorId !== (int) $user->id) {
            return false;
        }

        // 审核人不能审自己提交/执行的文件（禁止自审）
        return (int) $task->executor_id !== (int) $user->id
            && (int) $document->uploader_id !== (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasRole('role_sys_admin');
    }
}
