<?php

namespace App\Policies;

use App\Models\Organization\User;
use App\Models\Office\ChatRoom;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\System\MediaFile;

/**
 * P0-07 / B-02：文件按业务授权，不再"知道路径就能下"
 *  - resource / collab_doc：内部共享资料，登录即可见
 *  - chat_file：项目群聊/项目私聊的参与者可见
 *  - rnd_doc：只有该任务参与者（执行人/审核人）、项目创建人、研发经理、上传人可见
 *  - 其余（avatar/bom_excel…）：仅上传人本人
 */
class MediaFilePolicy
{
    public function view(User $user, MediaFile $file): bool
    {
        if (in_array($file->category, ['resource', 'collab_doc'], true)) {
            return true;
        }

        if ($file->category === 'chat_file') {
            return $this->chatAccess($user, $file);
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

    private function chatAccess(User $user, MediaFile $file): bool
    {
        if ((int) $file->uploader_id === (int) $user->id) {
            return true;
        }
        if ($user->hasRole('role_rnd_manager') || $user->hasRole('role_sys_admin')) {
            return true;
        }
        if ($file->ref_type !== 'chat_room' || ! $file->ref_id) {
            return false;
        }

        $room = ChatRoom::find($file->ref_id);
        if (! $room) {
            return false;
        }

        // 系统全员聊天室：所有已登录系统成员都可查看/下载其中的附件。
        if ($room->room_type === 'system_all') {
            return true;
        }

        $project = RndProject::withoutGlobalScopes()->find($room->related_id);
        if (! $project) {
            return false;
        }

        if ($room->room_type === 'rnd_private') {
            if (preg_match('/私聊:(\d+):(\d+)/', (string) $room->room_name, $m)) {
                return in_array((int) $user->id, [(int) $m[1], (int) $m[2]], true);
            }

            return false;
        }

        return in_array((int) $user->id, $project->participantIds(), true);
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
