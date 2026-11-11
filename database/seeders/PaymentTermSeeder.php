<?php

namespace Database\Seeders;

use App\Models\Finance\PaymentTerm;
use Illuminate\Database\Seeder;

/**
 * 结算方式基础数据：预付、货到付款、月结。
 */
class PaymentTermSeeder extends Seeder
{
    public function run(): void
    {
        $terms = [
            ['PREPAID', '预付100%', 0, '款到发货'],
            ['COD', '货到付款', 0, '验收后付款'],
            ['NET30', '月结30天', 30, '每月对账后30天付款'],
            ['NET60', '月结60天', 60, '每月对账后60天付款'],
            ['NET90', '月结90天', 90, '每月对账后90天付款'],
        ];

        foreach ($terms as [$code, $name, $days, $description]) {
            PaymentTerm::firstOrCreate(['code' => $code], [
                'name' => $name,
                'days' => $days,
                'description' => $description,
                'status' => true,
            ]);
        }
    }
}
