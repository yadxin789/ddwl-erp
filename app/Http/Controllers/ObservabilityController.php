<?php

namespace App\Http\Controllers;

use App\Models\Sync\SyncLog;
use App\Support\ObservabilityService;
use Illuminate\Http\Request;

/**
 * P2-05 可观测性看板：队列 / Outbox / 同步失败 / 审批停留；提供失败同步日志重放。
 */
class ObservabilityController extends Controller
{
    public function __construct(private readonly ObservabilityService $observability)
    {
    }

    public function index()
    {
        return view('system.observability', [
            'summary' => $this->observability->summary(),
            'queueFailures' => $this->observability->queueFailures(),
            'outboxFailures' => $this->observability->outboxFailures(),
            'syncFailures' => $this->observability->syncFailures(),
            'approval' => $this->observability->approvalStats(),
            'alerts' => $this->observability->recentAlerts(),
        ]);
    }

    public function replay(SyncLog $log)
    {
        if ($log->status !== 'failed') {
            return back()->with('error', '只有 failed 状态的同步日志可以重放。');
        }

        if ($log->replayed_at) {
            return back()->with('error', '该日志已在 ' . $log->replayed_at->format('Y-m-d H:i') . ' 重放过。');
        }

        SyncLog::create([
            'sync_task_id' => $log->sync_task_id,
            'run_at' => now(),
            'status' => 'queued',
            'total_count' => $log->total_count,
            'success_count' => 0,
            'message' => '重放入队：原日志 #' . $log->id . '；原因：' . mb_substr((string) $log->message, 0, 120),
            'replay_of' => $log->id,
        ]);

        $log->update(['replayed_at' => now()]);

        return back()->with('success', '已重新入队，等待同步执行器处理。');
    }
}
