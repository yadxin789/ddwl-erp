<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProjectTask;

/**
 * P0-05 / D-01：任务级授权
 * upload（提交交付物）：执行人本人或项目创建人；研发经理兜底。
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
     * P1-05：任务开始/提交 = 执行人本人（经理兜底）；任务通过/驳回 = 审核人、项目创建人、研发经理。
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
        if ($this->isManager($user) || (int) $task->auditor_id === (int) $user->id) {
            return true;
        }

        return (int) ($task->project?->creator_id ?? 0) === (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasPermissionTo('rnd.approve');
    }
}
