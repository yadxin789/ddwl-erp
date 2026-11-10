<?php

namespace Database\Seeders;

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Database\Seeder;

/**
 * 仓库 Seeder：原料仓/半成品仓/成品仓/不良品仓/退货仓
 * 每个仓库创建标准库位（待检区/合格区/冻结区）
 */
class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['warehouse_code' => 'WH-RM', 'name' => '原料仓', 'type' => 'raw_material'],
            ['warehouse_code' => 'WH-SF', 'name' => '半成品仓', 'type' => 'semi_finished'],
            ['warehouse_code' => 'WH-FG', 'name' => '成品仓', 'type' => 'finished_goods'],
            ['warehouse_code' => 'WH-RJ', 'name' => '不良品仓', 'type' => 'blocked'],
            ['warehouse_code' => 'WH-RT', 'name' => '退货仓', 'type' => 'return'],
        ];

        $zones = ['待检区' => 'QC', '合格区' => 'OK', '冻结区' => 'FZ'];

        foreach ($warehouses as $wh) {
            $warehouse = Warehouse::firstOrCreate(
                ['warehouse_code' => $wh['warehouse_code']],
                ['name' => $wh['name'], 'type' => $wh['type'], 'status' => 1]
            );

            // 每个仓库创建 3 个区域库位，如 WH-RM-QC-A01
            foreach ($zones as $zoneName => $zoneCode) {
                WarehouseLocation::firstOrCreate(
                    ['location_code' => $wh['warehouse_code'] . '-' . $zoneCode . '-A01'],
                    [
                        'warehouse_id' => $warehouse->id,
                        'zone' => $zoneName,
                        'status' => 1,
                    ]
                );
            }
        }
    }
}
