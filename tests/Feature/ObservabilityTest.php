<?php

namespace Tests\Feature;

use App\Models\Security\SecurityEvent;
use App\Models\Sync\SyncLog;
use App\Models\Sync\SyncTask;
use App\Models\Organization\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ObservabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_lists_three_sync_failures_and_replays_one(): void
    {
        DB::beginTransaction();
        try {
            $task = SyncTask::create([
                'name' => '可观测性演练-订单拉取',
                'target' => 'taobao',
                'direction' => 'pull',
                'data_type' => 'order',
                'cron_expr' => '*/10 * * * *',
                'status' => 'active',
            ]);

            $logs = [];
            for ($i = 1; $i <= 3; $i++) {
                $logs[] = SyncLog::create([
                    'sync_task_id' => $task->id,
                    'run_at' => now()->subMinutes($i * 5),
                    'status' => 'failed',
                    'total_count' => 10,
                    'success_count' => 0,
                    'message' => '连接超时（演练第 ' . $i . ' 次）',
                ]);
            }

            $admin = User::where('username', 'admin')->firstOrFail();
            $response = $this->actingAs($admin)->get(route('system.observability'));
            $response->assertOk();
            $response->assertSee('同步失败与重放');
            $response->assertSee('审批停留时长');
            $response->assertSee('可观测性演练-订单拉取');
            $response->assertSee('连接超时（演练第 1 次）');
            $response->assertSee('重放');
            $this->assertSame(3, SyncLog::where('sync_task_id', $task->id)->where('status', 'failed')->count());

            $target = $logs[0];
            $replay = $this->actingAs($admin)->post(route('sync.logs.replay', $target->id));
            $replay->assertRedirect();
            $this->assertNotNull($target->fresh()->replayed_at);
            $this->assertDatabaseHas('sync_logs', [
                'replay_of' => $target->id,
                'status' => 'queued',
                'sync_task_id' => $task->id,
            ]);

            $again = $this->actingAs($admin)->post(route('sync.logs.replay', $target->id));
            $again->assertRedirect();
            $again->assertSessionHas('error');
            $this->assertSame(1, SyncLog::where('replay_of', $target->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_scan_writes_security_event_over_threshold_and_dedupes(): void
    {
        DB::beginTransaction();
        try {
            $task = SyncTask::create([
                'name' => '告警演练',
                'target' => 'jd',
                'direction' => 'push',
                'data_type' => 'inventory',
                'status' => 'active',
            ]);

            for ($i = 0; $i < 3; $i++) {
                SyncLog::create([
                    'sync_task_id' => $task->id,
                    'run_at' => now(),
                    'status' => 'failed',
                    'total_count' => 1,
                    'success_count' => 0,
                    'message' => '推送失败',
                ]);
            }

            Artisan::call('erp:observe:scan', ['--threshold' => 3]);
            $this->assertDatabaseHas('security_events', [
                'event_type' => 'observability_alert',
                'level' => 'warning',
            ]);

            $before = SecurityEvent::where('event_type', 'observability_alert')->count();
            Artisan::call('erp:observe:scan', ['--threshold' => 3]);
            $this->assertSame($before, SecurityEvent::where('event_type', 'observability_alert')->count(), '1 小时内应去重');
        } finally {
            DB::rollBack();
        }
    }
}
