<?php

namespace App\Console\Commands;

use Database\Seeders\AcceptanceSeeder;
use Illuminate\Console\Command;

/**
 * P1-04：清理验收剧本数据（ACC-* 固定编码）。
 * 冒烟脚本跑完默认清理，避免验收数据留在演示库影响其他测试。
 */
class AcceptanceCleanCommand extends Command
{
    protected $signature = 'erp:acceptance:clean';

    protected $description = '清理 P1-04 验收剧本数据（AcceptanceSeeder 固定编码）';

    public function handle(): int
    {
        app(AcceptanceSeeder::class)->cleanup();
        $this->info('验收剧本数据已清理（ACC-*）。');

        return self::SUCCESS;
    }
}
