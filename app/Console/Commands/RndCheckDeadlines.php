<?php

namespace App\Console\Commands;

use App\Models\Rnd\RndProjectTask;
use App\Services\NotificationService;
use App\Services\RndProjectService;
use Illuminate\Console\Command;

class RndCheckDeadlines extends Command
{
    protected $signature = 'rnd:check-deadlines';
    protected $description = '每天检查研发任务截止日，标记逾期并通知执行人/项目负责人';

    public function handle(RndProjectService $service, NotificationService $notifier): int
    {
        // 1. 标记逾期
        $overdueCount = $service->markOverdue();
        $this->info("已标记逾期任务 {$overdueCount} 条");

        // 2. 给逾期任务执行人发通知
        RndProjectTask::where('is_overdue', true)
            ->whereNotIn('status', ['approved', 'completed', 'cancelled'])
            ->with(['project', 'taskType', 'executor'])
            ->each(function (RndProjectTask $task) use ($notifier) {
                $taskName = $task->name ?: ($task->taskType->name ?? '交付物');
                $notifier->send(
                    (int) $task->executor_id,
                    'rnd_overdue',
                    "研发任务逾期：{$taskName}",
                    "项目 {$task->project->project_name} 的任务已超过截止日 {$task->end_date}，请尽快处理。",
                    'rnd_task',
                    $task->id
                );
            });

        // 3. 临近到期（今天内到期但未完成）提醒
        $dueToday = RndProjectTask::whereDate('end_date', today())
            ->whereNotIn('status', ['approved', 'completed', 'cancelled'])
            ->with(['project', 'taskType', 'executor'])
            ->get();
        foreach ($dueToday as $task) {
            $taskName = $task->name ?: ($task->taskType->name ?? '交付物');
            $notifier->send(
                (int) $task->executor_id,
                'rnd_due_today',
                "今日到期：{$taskName}",
                "项目 {$task->project->project_name} 的任务今天到期，请尽快完成。",
                'rnd_task',
                $task->id
            );
        }
        $this->info("今日到期提醒 " . $dueToday->count() . " 条");

        return self::SUCCESS;
    }
}
