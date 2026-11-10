<?php

namespace Tests\Feature;

use App\Models\Ecom\PlatformInstance;
use App\Models\Organization\User;
use App\Services\Ecom\AdapterRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P1-07 电商接入向导 + 平台设置 + 适配器框架（本期不做交易闭环）
 */
class PlatformSetupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function operator(): User
    {
        return User::where('username', 'ecom-operator')->firstOrFail();
    }

    public function test_wizard_shows_domestic_and_overseas_presets(): void
    {
        $this->actingAs($this->operator());

        $this->get(route('ecom.onboarding'))
            ->assertOk()
            ->assertSee('淘宝 / 天猫')
            ->assertSee('Amazon')
            ->assertSee('国内平台')
            ->assertSee('国外平台')
            ->assertSee('手动添加任意平台');

        // 第 2 步：凭证页展示所需凭证与申请入口
        $this->get(route('ecom.onboarding.credentials', 'taobao'))
            ->assertOk()
            ->assertSee('App Key')
            ->assertSee('open.taobao.com');

        // 平台设置页（P3-08 起有离线演示店铺）
        $this->get(route('ecom.instances'))->assertOk()->assertSee('演示店铺（离线）');
    }

    public function test_wizard_saves_instance_with_encrypted_credentials(): void
    {
        $this->actingAs($this->operator());

        $this->post(route('ecom.onboarding.store', 'taobao'), [
            'instance_name' => '旗舰店-淘宝',
            'credentials' => ['app_key' => 'ak-123', 'app_secret' => 'sec-456', 'session_key' => 'sess-789'],
            'sync_frequency' => 'manual',
        ])->assertRedirect(route('ecom.instances'));

        $instance = PlatformInstance::where('platform_key', 'taobao')->firstOrFail();
        $this->assertSame('淘宝 / 天猫', (new AdapterRegistry)->preset('taobao')['name']);
        $this->assertSame('ak-123', $instance->credentials_json['app_key']);

        // 加密存储：数据库原始值不是明文
        $raw = DB::table('platform_instances')->where('id', $instance->id)->value('credentials_json');
        $this->assertStringNotContainsString('ak-123', (string) $raw, '凭证必须加密存储');
        $this->assertSame('ok', $instance->last_check_result);
        $this->assertTrue($instance->status);

        // 停用后不产生同步任务（本期整体不产生同步任务）
        $this->post(route('ecom.instances.toggle', $instance->id), ['status' => 0])
            ->assertRedirect();
        $this->assertFalse(PlatformInstance::find($instance->id)->status);
        if (Schema::hasTable('sync_tasks')) {
            $this->assertSame(0, DB::table('sync_tasks')->count());
        }
    }

    public function test_bad_credentials_returns_readable_error_not_500(): void
    {
        $this->actingAs($this->operator());

        // 缺 app_secret → 200 + 中文可读错误（不 500）
        $response = $this->post(route('ecom.onboarding.store', 'jd'), [
            'instance_name' => '京东店',
            'credentials' => ['app_key' => 'only-key'],
            'sync_frequency' => 'manual',
        ]);
        $response->assertStatus(200);
        $response->assertSee('缺少必填凭证');
        $this->assertDatabaseMissing('platform_instances', ['platform_key' => 'jd']);
    }

    public function test_custom_platform_is_recognized_by_registry(): void
    {
        config(['platforms.probe_enabled' => false]);   // 离线/测试模式：只校验配置完整性
        $this->actingAs($this->operator());

        $this->post(route('ecom.onboarding.custom.store'), [
            'name' => '客户自有商城',
            'platform_key' => 'my-mall',
            'api_base_url' => 'https://mall.example.com/api',
            'auth_type' => 'apikey',
            'api_key' => 'token-abc',
            'region' => 'domestic',
            'sync_frequency' => 'manual',
            'field_mapping' => '{"order_no":"data.orderId","sku":"data.skuCode"}',
            'status_mapping' => '{"PAID":"paid"}',
        ])->assertRedirect(route('ecom.instances'));

        $this->assertDatabaseHas('platform_instances', ['platform_key' => 'custom:my-mall']);

        // Registry 能识别并给出适配器
        $registry = new AdapterRegistry;
        $this->assertTrue($registry->has('custom:my-mall'));
        $adapter = $registry->make('custom:my-mall', [
            'api_base_url' => 'https://mall.example.com/api',
            'status_mapping' => ['PAID' => 'paid'],
        ]);
        $this->assertNotNull($adapter);
        $this->assertSame('custom:my-mall', $adapter->key());
        $this->assertTrue($adapter->capabilities()['pullOrders'], 'P3-08 起订单拉取能力开启');
        $this->assertSame('paid', $adapter->mapStatus('PAID'));
    }

    public function test_contract_for_all_presets(): void
    {
        $registry = new AdapterRegistry;
        $presets = $registry->presets();
        $this->assertNotEmpty($presets);

        foreach ($presets as $key => $definition) {
            $adapter = $registry->make($key);
            $this->assertNotNull($adapter, "预置 {$key} 必须有适配器");
            $caps = $adapter->capabilities();
            foreach (['pullOrders', 'pushShipment'] as $cap) {
                $this->assertArrayHasKey($cap, $caps, "{$key} 缺少能力声明 {$cap}");
                $this->assertTrue($caps[$cap], "{$key} 的 {$cap} 在 P3-08 应开启");
            }
            $this->assertArrayHasKey('ackOrder', $caps);
            $this->assertFalse($caps['ackOrder'], "{$key} 的 ackOrder 仍为待接入");

            // 缺凭证必须给可读错误，不能抛异常
            $check = $adapter->verifyCredentials([]);
            $this->assertFalse($check['ok']);
            $this->assertNotSame('', $check['message']);
            $this->assertArrayHasKey('code', $check);

            // 带齐凭证 → 通过
            $full = [];
            foreach ($definition['required_credentials'] as $field => $label) {
                $full[$field] = 'mock-' . $field;
            }
            $this->assertTrue($adapter->verifyCredentials($full)['ok'], "{$key} 带齐凭证应通过自检");
        }
    }
}
