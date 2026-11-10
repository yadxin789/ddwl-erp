<?php

namespace App\Console\Commands;

use App\Support\ObservabilityService;
use Illuminate\Console\Command;

/**
 * P2-05 可观测性巡检：汇总队列/Outbox/同步失败，达到阈值写 security_events。
 *
 * 用法：php artisan erp:observe:scan [--threshold=3]
 * 退出码：0（巡检本身不阻断）；如需 CI 用 --strict 再议。
 */
class ObserveScan extends Command
{
    protected $signature = 'erp:observe:scan {--threshold=3 : 失败总数达到该值写告警}';

    protected $description = '巡检队列/Outbox/同步失败并写安全事件告警';

    public function handle(ObservabilityService $observability): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $summary = $observability->summary();

        $this->line(sprintf(
            '队列失败 %d / 积压 %d；Outbox 失败 %d / 待发 %d；同步失败(24h) %d；待审批 %d',
            $summary['failed_jobs'], $summary['queued_jobs'],
            $summary['outbox_failed'], $summary['outbox_pending'],
            $summary['sync_failed_24h'], $summary['approval_pending']
        ));

        $result = $observability->alertIfNeeded($threshold);
        if (! $result['triggered']) {
            $this->info('失败总数未达阈值，未告警。');

            return 0;
        }

        if ($result['created']) {
            $this->warn('已写告警 security_events（observability_alert）。');
        } else {
            $this->warn('1 小时内已有同类告警，本次去重不再重复写。');
        }

        return 0;
    }
}
