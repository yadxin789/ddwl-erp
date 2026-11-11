<?php

namespace Tests\Feature;

use App\Models\Ecom\EcomOrder;
use App\Models\Ecom\EcomReconciliationRun;
use App\Models\Ecom\PlatformInstance;
use App\Models\Event\IntegrationOutbox;
use App\Models\MasterData\Material;
use App\Models\Organization\User;
use App\Services\Ecom\EcomOrderSyncService;
use App\Services\Ecom\EcomOutboxService;
use App\Services\Ecom\EcomReconciliationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** P3-08 电商交易闭环：拉单幂等、发货回写重试/死信、对账。 */
class EcomClosedLoopTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // 演示库自带离线店铺的待回写任务，测试只处理本用例产生的记录
        IntegrationOutbox::where('event_type', 'ecom.shipment.push')->delete();
    }

    private function qaInstance(): PlatformInstance
    {        return PlatformInstance::create([
            'platform_key' => 'custom:qa-shop',
            'name' => 'QA 测试店铺',
            'region' => 'custom',
            'credentials_json' => ['api_key' => 'qa-token'],
            'mapping_json' => [],
            'api_base_url' => 'https://qa.example.com',
            'auth_type' => 'apikey',
            'sync_frequency' => 'manual',
            'status' => true,
            'last_check_at' => now(),
            'last_check_result' => 'ok',
        ]);
    }

    public function test_pull_orders_is_idempotent_and_queues_shipped_shipment(): void
    {
        $instance = $this->qaInstance();
        Http::fake([
            'https://qa.example.com/orders*' => Http::response(['orders' => [
                [
                    'order_no' => 'QA-EC-001', 'status' => 'paid', 'total_amount' => 100, 'pay_amount' => 100,
                    'buyer_nick' => 'buyer-a', 'receiver_name' => '张三', 'receiver_phone' => '13800000000',
                    'items' => [['outer_sku' => 'EL000003', 'product_name' => 'MCU', 'qty' => 2, 'price' => 50]],
                ],
                [
                    'order_no' => 'QA-EC-002', 'status' => 'shipped', 'total_amount' => 120, 'pay_amount' => 120,
                    'buyer_nick' => 'buyer-b', 'receiver_name' => '李四',
                    'tracking_no' => 'SF-QA-1',
                    'items' => [['outer_sku' => 'NO-SUCH-SKU', 'product_name' => '散件', 'qty' => 2, 'price' => 50]],
                ],
            ]]),
        ]);

        $result = app(EcomOrderSyncService::class)->sync($instance);
        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['created']);
        $this->assertSame(1, $result['queued']);

        $paid = EcomOrder::where('ecom_order_no', 'QA-EC-001')->firstOrFail();
        $this->assertSame('paid', $paid->status);
        $this->assertSame('pulled', $paid->sync_status);
        $this->assertSame(100.0, (float) $paid->pay_amount);
        $this->assertSame(1, $paid->items()->count());
        $this->assertSame(1, (int) $paid->items()->value('sku_mapped'));
        $this->assertSame(
            Material::where('material_code', 'EL000003')->value('id'),
            (int) $paid->items()->value('material_id')
        );

        // 二次拉取：更新而不是新增；已发货订单不重复排 outbox
        $again = app(EcomOrderSyncService::class)->sync($instance);
        $this->assertTrue($again['ok']);
        $this->assertSame(0, $again['created']);
        $this->assertSame(2, $again['updated']);
        $this->assertSame(0, $again['queued']);
        $this->assertSame(2, EcomOrder::where('ecom_order_no', 'like', 'QA-EC-%')->count());
        $this->assertSame(1, IntegrationOutbox::where('event_type', 'ecom.shipment.push')->count());
        $this->assertSame('shipped', EcomOrder::where('ecom_order_no', 'QA-EC-002')->firstOrFail()->sync_status);
    }

    public function test_shipment_push_success_marks_order_pushed(): void
    {
        $instance = $this->qaInstance();
        $order = EcomOrder::create([
            'ecom_order_no' => 'QA-EC-003', 'platform_id' => 1, 'platform_instance_id' => $instance->id,
            'total_amount' => 10, 'pay_amount' => 10, 'status' => 'shipped', 'sync_status' => 'shipped',
            'tracking_no' => 'SF-QA-2',
        ]);
        $this->assertSame(1, app(EcomOrderSyncService::class)->enqueueShipment($order));

        Http::fake(['https://qa.example.com/shipments' => Http::response(['ok' => true], 200)]);
        $sent = app(EcomOutboxService::class)->dispatchPending();
        $this->assertSame(1, $sent['sent']);
        $this->assertSame('published', IntegrationOutbox::where('ref_id', $order->id)->firstOrFail()->status);
        $this->assertSame('pushed', $order->fresh()->sync_status);
        $this->assertNotNull($order->fresh()->pushed_at);
    }

    public function test_shipment_failures_retry_until_dead_and_can_be_reset(): void
    {
        $instance = $this->qaInstance();
        $bad = EcomOrder::create([
            'ecom_order_no' => 'QA-EC-004', 'platform_id' => 1, 'platform_instance_id' => $instance->id,
            'total_amount' => 20, 'pay_amount' => 20, 'status' => 'shipped', 'sync_status' => 'shipped',
        ]);
        app(EcomOrderSyncService::class)->enqueueShipment($bad);
        Http::fake(['https://qa.example.com/shipments' => Http::response('platform error', 500)]);

        for ($i = 0; $i < 5; $i++) {
            $this->travel(40)->minutes();
            app(EcomOutboxService::class)->dispatchPending();
        }

        $dead = IntegrationOutbox::where('ref_id', $bad->id)->firstOrFail();
        $this->assertSame('dead', $dead->status);
        $this->assertSame(5, $dead->retry_count);
        $this->assertNotNull($dead->dead_at);
        $this->assertSame('error', $bad->fresh()->sync_status);

        $this->assertSame(1, app(EcomOutboxService::class)->retryDead());
        $this->assertSame('pending', $dead->fresh()->status);
        $this->assertSame(0, $dead->fresh()->retry_count);
    }

    public function test_reconciliation_flags_header_line_mismatch_and_is_idempotent(): void
    {
        $instance = $this->qaInstance();
        EcomOrder::create(['ecom_order_no' => 'QA-EC-010', 'platform_id' => 1, 'platform_instance_id' => $instance->id, 'total_amount' => 100, 'pay_amount' => 100, 'status' => 'paid'])
            ->items()->create(['outer_sku' => 'EL000003', 'product_name' => 'MCU', 'qty' => 2, 'price' => 50]);
        EcomOrder::create(['ecom_order_no' => 'QA-EC-011', 'platform_id' => 1, 'platform_instance_id' => $instance->id, 'total_amount' => 120, 'pay_amount' => 120, 'status' => 'shipped'])
            ->items()->create(['outer_sku' => 'X', 'product_name' => '散件', 'qty' => 2, 'price' => 50]);

        $run = app(EcomReconciliationService::class)->run($instance, today(), today());
        $this->assertSame(2, $run->order_count);
        $this->assertSame(2, $run->paid_count);
        $this->assertSame(1, $run->shipped_count);
        $this->assertSame(220.0, (float) $run->header_amount);
        $this->assertSame(200.0, (float) $run->item_amount);
        $this->assertSame(1, $run->mismatch_count);
        $this->assertSame('mismatch', $run->result);
        $this->assertSame('QA-EC-011', $run->summary_json['mismatches'][0]['order_no']);

        app(EcomReconciliationService::class)->run($instance, today(), today());
        $this->assertSame(1, EcomReconciliationRun::where('platform_instance_id', $instance->id)->count());
    }

    public function test_ecom_orders_page_renders_closed_loop_sections(): void
    {
        $operator = User::where('username', '魏晨')->firstOrFail();
        $this->actingAs($operator)->get(route('ecom.orders'))
            ->assertOk()
            ->assertSee('发货回写')
            ->assertSee('订单对账');
    }
}

