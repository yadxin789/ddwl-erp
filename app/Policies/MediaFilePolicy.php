<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\System\MediaFile;

/**
 * P0-07 / B-02：文件按业务授权，不再"知道路径就能下"
 *  - resource / collab_doc：内部共享资料，登录即可见
 *  - rnd_doc：只有该任务参与者（执行人/审核人）、项目创建人、研发经理、上传人可见
 *  - 其余（avatar/chat_file/bom_excel…）：仅上传人本人
 */
class MediaFilePolicy
{
    public function view(User $user, MediaFile $file): bool
    {
        if (in_array($file->category, ['resource', 'collab_doc'], true)) {
            return true;
        }

        if ($file->category === 'rnd_doc') {
            return $this->rndAccess($user, $file);
        }

        return (int) $file->uploader_id === (int) $user->id;
    }

    public function download(User $user, MediaFile $file): bool
    {
        return $this->view($user, $file);
    }

    private function rndAccess(User $user, MediaFile $file): bool
    {
        if ($this->isManager($user)) {
            return true;
        }
        if ((int) $file->uploader_id === (int) $user->id) {
            return true;
        }

        $task = null;
        if ($file->ref_type === 'rnd_document' && $file->ref_id) {
            $task = RndProjectTask::find($file->ref_id);
        }
        if (! $task) {
            // 老数据没有 ref 指针：用交付物记录反查（此处必须绕过行级范围，否则审核人查不到）
            $doc = RndProjectDocument::withoutGlobalScopes()->where('file_id', $file->id)->first();
            $task = $doc?->task;
        }
        if (! $task) {
            return false;
        }

        if ((int) $task->executor_id === (int) $user->id || (int) $task->auditor_id === (int) $user->id) {
            return true;
        }

        return (int) ($task->project?->creator_id ?? 0) === (int) $user->id;
    }

    private function isManager(User $user): bool
    {
        return $user->hasRole('role_rnd_manager') || $user->hasPermissionTo('rnd.approve');
    }
}
