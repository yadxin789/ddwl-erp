<?php

namespace Database\Seeders;

use App\Models\Finance\TaxEntity;
use App\Models\Finance\TaxRate;
use Illuminate\Database\Seeder;

/**
 * 税率基础数据：税目 + 税率。
 * 只补缺失，不覆盖后台改过的名称、税率与启停状态。
 */
class TaxMasterSeeder extends Seeder
{
    public function run(): void
    {
        $entities = [
            ['VAT_GENERAL', '增值税（一般纳税人）', 'vat'],
            ['VAT_SMALL', '增值税（小规模纳税人）', 'vat'],
            ['ZERO_RATE', '零税率', 'zero'],
        ];

        foreach ($entities as [$code, $name, $type]) {
            TaxEntity::firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'status' => true,
            ]);
        }

        $rates = [
            ['VAT_GENERAL', '标准税率', 13.000],
            ['VAT_GENERAL', '低税率', 9.000],
            ['VAT_GENERAL', '服务税率', 6.000],
            ['VAT_SMALL', '征收率', 3.000],
            ['ZERO_RATE', '零税率', 0.000],
        ];

        foreach ($rates as [$entityCode, $taxType, $rate]) {
            $entity = TaxEntity::where('code', $entityCode)->first();
            if (! $entity) {
                continue;
            }
            TaxRate::firstOrCreate(
                ['tax_entity_id' => $entity->id, 'tax_type' => $taxType],
                ['rate' => $rate, 'status' => true]
            );
        }
    }
}
