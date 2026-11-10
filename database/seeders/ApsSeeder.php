<?php

namespace Database\Seeders;

use App\Models\Production\Routing;
use App\Models\Production\RoutingOperation;
use App\Models\Production\WorkCenter;
use App\Models\MasterData\Material;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * P3-2 APS 基础数据：工作中心 + 验收成品 FG01 的工艺路线。
 * 只用于演示/验收，不写工单数量，避免改动 P1-04 验收数据口径。
 */
class ApsSeeder extends Seeder
{
    public const ROUTING_CODE = 'RT-ACC-FG01';

    public function run(): void
    {
        $centers = [
            ['code' => 'WC-SMT', 'name' => 'SMT 贴片线', 'location' => 'A 区', 'capacity_per_day' => 4],
            ['code' => 'WC-ASSY', 'name' => '装配线', 'location' => 'B 区', 'capacity_per_day' => 4],
            ['code' => 'WC-TEST', 'name' => '测试工位', 'location' => 'C 区', 'capacity_per_day' => 4],
        ];
        foreach ($centers as $row) {
            WorkCenter::updateOrCreate(['code' => $row['code']], $row + ['status' => true]);
        }

        $product = Material::where('material_code', 'ACC-FG01')->first();
        if (! $product) {
            return;
        }

        $routing = Routing::updateOrCreate(
            ['routing_code' => self::ROUTING_CODE],
            ['product_material_id' => $product->id, 'version' => 'v1.0', 'status' => 'approved']
        );

        if ($routing->operations()->count() === 0) {
            $ops = [
                [1, 'SMT 贴片', 'WC-SMT', 0.2],
                [2, '整机装配', 'WC-ASSY', 0.5],
                [3, '功能测试', 'WC-TEST', 0.2],
            ];
            foreach ($ops as [$seq, $name, $centerCode, $hours]) {
                RoutingOperation::create([
                    'routing_id' => $routing->id,
                    'operation_seq' => $seq,
                    'operation_name' => $name,
                    'work_center_id' => WorkCenter::where('code', $centerCode)->value('id'),
                    'standard_hours' => $hours,
                ]);
            }
        }

        DB::table('production_orders')
            ->where('order_no', AcceptanceSeeder::PRODUCTION_ORDER_NO)
            ->whereNull('routing_id')
            ->update(['routing_id' => $routing->id, 'updated_at' => now()]);

        $this->command?->info('P3-2 APS 基础数据：3 工作中心 / 1 工艺路线（3 工序）。');
    }
}
