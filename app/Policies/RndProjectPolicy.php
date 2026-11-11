<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProject;

/**
 * P0-05 / D-01：研发项目对象级授权
 * view：创建人、任务执行人/审核人、研发经理（系统管理员）；createTask：创建人或研发经理。
 * 注意：rnd.approve 只代表“可审核”，不再等同于“研发经理”，避免各部门审核人越权看全部项目。
 */
class RndProjectPolicy
{
    public function view(User $user, RndProject $project): bool
    {
        if ($this->isManager($user)) {
            return true;
        }

        if ((int) $project->creator_id === (int) $user->id) {
            return true;
        }

        return $project->tasks()
            ->where(function ($q) use ($user) {
                $q->where('executor_id', $user->id)->orWhere('auditor_id', $user->id);
            })
            ->exists();
    }

    public function createTask(User $user, RndProject $project): bool
    {
        return $this->isManager($user) || (int) $project->creator_id === (int) $user->id;
    }

    /** P1-05：阶段推进 / 设基线 / 提变更单 = 项目创建人或研发经理 */
    public function manage(User $user, RndProject $project): bool
    {
        return $this->isManager($user) || (int) $project->creator_id === (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasRole('role_sys_admin');
    }
}
