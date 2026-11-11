<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProjectTask;

/**
 * P0-05 / D-01：任务级授权
 * upload（提交交付物）：执行人本人或项目创建人；研发经理兜底。
 * decide（通过/驳回）：只允许该任务指定的审核人本人，且不能是执行人自审；
 * 项目创建人、研发经理均不能代替部门经理审核。
 */
class RndProjectTaskPolicy
{
    public function view(User $user, RndProjectTask $task): bool
    {
        return $this->upload($user, $task);
    }

    public function upload(User $user, RndProjectTask $task): bool
    {
        if ($this->isManager($user)) {
            return true;
        }
        if ((int) $task->executor_id === (int) $user->id) {
            return true;
        }

        return (int) ($task->project?->creator_id ?? 0) === (int) $user->id;
    }

    /**
     * P1-05：任务开始/提交 = 执行人本人（经理兜底）；任务通过/驳回 = 指定审核人本人。
     */
    public function execute(User $user, RndProjectTask $task): bool
    {
        if ($this->isManager($user) || (int) $task->executor_id === (int) $user->id) {
            return true;
        }

        return (int) ($task->project?->creator_id ?? 0) === (int) $user->id;
    }

    public function decide(User $user, RndProjectTask $task): bool
    {
        $auditorId = (int) ($task->auditor_id ?? 0);

        if ($auditorId === 0 || $auditorId !== (int) $user->id) {
            return false;
        }

        // 审核人不能同时是执行人（禁止自审）
        return (int) $task->executor_id !== (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasRole('role_sys_admin');
    }
}
