<?php

namespace App\Console\Commands;

use App\Models\Ecom\PlatformInstance;
use App\Services\Ecom\EcomOrderSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** P3-08 按平台实例同步频率拉取订单（manual 只处理 --instance 指定）。 */
class EcomPullOrders extends Command
{
    protected $signature = 'erp:ecom:pull-orders {--instance= : 只拉取指定实例 ID（忽略频率）}';

    protected $description = '按 sync_frequency 拉取电商平台订单并落库';

    public function handle(EcomOrderSyncService $sync): int
    {
        $query = PlatformInstance::where('status', true)->orderBy('id');
        if ($this->option('instance')) {
            $query->whereKey((int) $this->option('instance'));
        }
        $instances = $query->get();

        $pulled = 0;
        $created = 0;
        $failed = 0;

        foreach ($instances as $instance) {
            if (! $this->option('instance') && ! $this->isDue($instance)) {
                continue;
            }

            $result = $sync->sync($instance);
            $pulled++;
            if ($result['ok']) {
                $created += $result['created'];
                $this->line("{$instance->name}：新增 {$result['created']}、更新 {$result['updated']}、待回写 {$result['queued']}");
            } else {
                $failed++;
                $this->warn("{$instance->name}：{$result['message']}");
            }
        }

        $this->info("拉取 {$pulled} 个实例，新增 {$created} 单，失败 {$failed} 个。");

        return self::SUCCESS;
    }

    private function isDue(PlatformInstance $instance): bool
    {
        $minutes = match ($instance->sync_frequency) {
            '15m' => 15,
            'hourly' => 60,
            'daily' => 1440,
            default => null,
        };
        if ($minutes === null) {
            return false;
        }

        return $instance->last_synced_at === null
            || $instance->last_synced_at->lte(Carbon::now()->subMinutes($minutes));
    }
}
