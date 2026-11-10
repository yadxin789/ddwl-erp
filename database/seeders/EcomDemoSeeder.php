<?php

namespace Database\Seeders;

use App\Models\Ecom\PlatformInstance;
use App\Services\Ecom\EcomOrderSyncService;
use Illuminate\Database\Seeder;

/**
 * P3-08 电商闭环演示数据：一个离线店铺 + 3 笔订单（1 笔头行金额不一致用于对账演示）。
 * 不配置 api_base_url，避免演示环境向外部平台发起真实请求。
 */
class EcomDemoSeeder extends Seeder
{
    public function run(): void
    {
        $instance = PlatformInstance::updateOrCreate(
            ['platform_key' => 'custom:demo-shop'],
            [
                'name' => '演示店铺（离线）',
                'region' => 'custom',
                'credentials_json' => ['api_key' => 'demo-only'],
                'mapping_json' => [],
                'api_base_url' => null,
                'auth_type' => 'apikey',
                'sync_frequency' => 'manual',
                'status' => true,
                'last_check_at' => now(),
                'last_check_result' => 'ok',
                'last_check_message' => '演示数据：未配置接口地址，不发起真实请求。',
            ]
        );

        app(EcomOrderSyncService::class)->ingest($instance, [
            [
                'order_no' => 'DEMO-EC-001', 'status' => 'paid', 'total_amount' => 100, 'pay_amount' => 100,
                'buyer_nick' => 'demo_buyer_a', 'receiver_name' => '演示客户A', 'receiver_phone' => '13800000001',
                'receiver_address' => '深圳市南山区演示路 1 号',
                'items' => [['outer_sku' => 'EL000003', 'product_name' => 'MCU微控制器', 'qty' => 2, 'price' => 50]],
            ],
            [
                'order_no' => 'DEMO-EC-002', 'status' => 'shipped', 'total_amount' => 120, 'pay_amount' => 120,
                'buyer_nick' => 'demo_buyer_b', 'receiver_name' => '演示客户B', 'receiver_phone' => '13800000002',
                'receiver_address' => '深圳市宝安区演示路 2 号', 'tracking_no' => 'SF-DEMO-002',
                'items' => [['outer_sku' => 'NO-MAPPED-SKU', 'product_name' => '演示散件', 'qty' => 2, 'price' => 50]],
            ],
            [
                'order_no' => 'DEMO-EC-003', 'status' => 'completed', 'total_amount' => 200, 'pay_amount' => 200,
                'buyer_nick' => 'demo_buyer_c', 'receiver_name' => '演示客户C',
                'items' => [['outer_sku' => 'EL000001', 'product_name' => '贴片电阻(K)', 'qty' => 100, 'price' => 2]],
            ],
        ]);
    }
}
