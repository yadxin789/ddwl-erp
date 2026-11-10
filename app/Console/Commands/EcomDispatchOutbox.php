<?php

namespace App\Console\Commands;

use App\Services\Ecom\EcomOutboxService;
use Illuminate\Console\Command;

/** P3-08 电商发货回写：后台处理 outbox，指数退避重试，超限转死信。 */
class EcomDispatchOutbox extends Command
{
    protected $signature = 'erp:ecom:dispatch-outbox {--limit=50 : 单次最多处理条数}';

    protected $description = '处理电商发货回写 outbox（pending/failed → published/dead）';

    public function handle(EcomOutboxService $outbox): int
    {
        $result = $outbox->dispatchPending((int) $this->option('limit'));

        $this->info("处理 {$result['processed']} 条：成功 {$result['sent']}、失败 {$result['failed']}、死信 {$result['dead']}。");

        return self::SUCCESS;
    }
}
