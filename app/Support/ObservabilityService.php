<?php

namespace App\Support;

use App\Models\Approval\ApprovalInstance;
use App\Models\Event\IntegrationOutbox;
use App\Models\Security\SecurityEvent;
use App\Models\Sync\SyncLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P2-05 可观测性：队列 / Outbox / 同步失败汇总 + 审批停留时长 + 阈值告警。
 *
 * 只读诊断，不改业务数据；告警写 security_events（event_type=observability_alert）。
 */
class ObservabilityService
{
    public const ALERT_EVENT = 'observability_alert';

    public function summary(): array
    {
        $failedJobs = DB::table('failed_jobs')->count();
        $queuedJobs = DB::table('jobs')->count();
        $outboxPending = IntegrationOutbox::where('status', 'pending')->count();
        $outboxFailed = IntegrationOutbox::where('status', 'failed')->count();
        $syncFailed24h = SyncLog::where('status', 'failed')
            ->where('run_at', '>=', now()->subDay())
            ->count();
        $syncQueued = SyncLog::where('status', 'queued')->count();
        $approvalPending = ApprovalInstance::where('status', 'pending')->count();

        return [
            'failed_jobs' => $failedJobs,
            'queued_jobs' => $queuedJobs,
            'outbox_pending' => $outboxPending,
            'outbox_failed' => $outboxFailed,
            'sync_failed_24h' => $syncFailed24h,
            'sync_queued' => $syncQueued,
            'approval_pending' => $approvalPending,
            'failure_total' => $failedJobs + $outboxFailed + $syncFailed24h,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function queueFailures(int $limit = 10): array
    {
        return DB::table('failed_jobs')->orderByDesc('failed_at')->limit($limit)->get()
            ->map(function ($row) {
                $payload = json_decode((string) $row->payload, true) ?: [];
                $exception = trim((string) $row->exception);
                $firstLine = preg_split('/\r?\n/', $exception)[0] ?? '';

                return [
                    'id' => $row->id,
                    'queue' => $row->queue,
                    'name' => $payload['displayName'] ?? '（未知任务）',
                    'exception' => mb_substr($firstLine, 0, 200),
                    'failed_at' => $row->failed_at,
                ];
            })->all();
    }

    public function outboxFailures(int $limit = 10): array
    {
        return IntegrationOutbox::whereIn('status', ['failed', 'pending'])
            ->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'event_type' => $row->event_type,
                'status' => $row->status,
                'retry_count' => $row->retry_count,
                'updated_at' => $row->updated_at?->toDateTimeString(),
            ])->all();
    }

    public function syncFailures(int $limit = 20): array
    {
        return SyncLog::with('task')
            ->whereIn('status', ['failed', 'queued'])
            ->orderByDesc('run_at')->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'task' => $row->task?->name ?? ('#' . $row->sync_task_id),
                'target' => $row->task?->target,
                'status' => $row->status,
                'message' => mb_substr((string) $row->message, 0, 200),
                'run_at' => $row->run_at?->toDateTimeString(),
                'replay_of' => $row->replay_of,
                'replayed_at' => $row->replayed_at?->toDateTimeString(),
            ])->all();
    }

    public function approvalStats(): array
    {
        $pending = ApprovalInstance::where('status', 'pending')->get(['id', 'business_type', 'created_at']);
        $ages = $pending->map(fn ($row) => $row->created_at ? now()->diffInMinutes($row->created_at) : 0);

        $decided = ApprovalInstance::whereIn('status', ['approved', 'rejected'])
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['id', 'business_type', 'status', 'created_at', 'approved_at', 'updated_at']);

        $byType = [];
        foreach ($decided as $row) {
            $end = $row->approved_at ?: $row->updated_at;
            if (! $row->created_at || ! $end) {
                continue;
            }
            $hours = $row->created_at->diffInMinutes($end) / 60;
            $type = $row->business_type ?: '未分类';
            $byType[$type][] = $hours;
        }

        $avg = [];
        foreach ($byType as $type => $hours) {
            $avg[$type] = [
                'count' => count($hours),
                'avg_hours' => round(array_sum($hours) / count($hours), 2),
                'max_hours' => round(max($hours), 2),
            ];
        }

        return [
            'pending' => $pending->count(),
            'pending_avg_hours' => $ages->count() ? round($ages->avg() / 60, 2) : 0,
            'pending_max_hours' => $ages->count() ? round($ages->max() / 60, 2) : 0,
            'pending_over_3d' => $ages->filter(fn ($m) => $m > 3 * 24 * 60)->count(),
            'decided_by_type' => $avg,
        ];
    }

    /**
     * 失败总数达到阈值时写一条安全事件（1 小时内去重）。
     *
     * @return array{triggered:bool,created:bool,detail:array}
     */
    public function alertIfNeeded(int $threshold = 3): array
    {
        $summary = $this->summary();
        $total = $summary['failure_total'];
        $detail = [
            'failed_jobs' => $summary['failed_jobs'],
            'outbox_failed' => $summary['outbox_failed'],
            'sync_failed_24h' => $summary['sync_failed_24h'],
            'threshold' => $threshold,
            'generated_at' => $summary['generated_at'],
        ];

        if ($total < $threshold) {
            return ['triggered' => false, 'created' => false, 'detail' => $detail];
        }

        $recent = SecurityEvent::where('event_type', self::ALERT_EVENT)
            ->where('created_at', '>=', now()->subHour())
            ->exists();

        if ($recent) {
            return ['triggered' => true, 'created' => false, 'detail' => $detail];
        }

        SecurityEvent::create([
            'event_type' => self::ALERT_EVENT,
            'user_id' => null,
            'ip_address' => null,
            'level' => $total >= $threshold * 2 ? 'critical' : 'warning',
            'detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        return ['triggered' => true, 'created' => true, 'detail' => $detail];
    }

    public function recentAlerts(int $limit = 10): array
    {
        return SecurityEvent::where('event_type', self::ALERT_EVENT)
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'level' => $row->level,
                'detail' => $row->detail,
                'created_at' => $row->created_at?->toDateTimeString(),
            ])->all();
    }
}
