<?php

namespace Tests\Feature;

use App\Models\Ai\AiConversation;
use App\Models\MasterData\Customer;
use App\Models\Organization\User;
use App\Models\Sales\SalesOrder;
use App\Services\BusinessQaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** P3-5 AI 助手业务问答：有数据出处、受权限与数据范围约束。 */
class BusinessQaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_sales_and_inventory_answers_are_grounded_in_erp_data(): void
    {
        $sales = User::where('username', 'sales')->firstOrFail();
        $customer = Customer::where('customer_code', 'ACC-C01')->firstOrFail();
        // 事务内清掉演示订单，保证金额断言只受本用例数据影响（结束后回滚）
        SalesOrder::withoutGlobalScopes()->delete();

        SalesOrder::create([
            'order_no' => 'QA-SO-001',
            'customer_id' => $customer->id,
            'order_date' => today(),
            'status' => 'confirmed',
            'total_amount' => 1234.56,
            'salesperson_id' => $sales->id,
        ]);

        $this->actingAs($sales);
        $response = $this->post(route('ai.chat.send'), ['content' => '本月销售额多少？']);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('销售汇总')
            ->assertSee('1,234.56')
            ->assertSee('口径');

        $conv = AiConversation::where('user_id', $sales->id)->orderByDesc('id')->firstOrFail();
        $inventory = $this->post(route('ai.chat.send'), [
            'content' => 'EL000003 还有多少库存？',
            'conversation_id' => $conv->id,
        ]);
        $inventory->assertRedirect();
        $this->get($inventory->headers->get('Location'))
            ->assertOk()
            ->assertSee('物料库存 · EL000003')
            ->assertSee('200 PCS');

        $assistant = $conv->messages()->where('role', 'assistant')->get();
        $this->assertCount(2, $assistant);
        $this->assertStringContainsString('1,234.56', $assistant[0]->content);
        $this->assertStringContainsString('200 PCS', $assistant[1]->content);
    }

    public function test_approval_and_production_intents_are_supported(): void
    {
        $keeper = User::where('username', 'wh-keeper')->firstOrFail();
        $approval = app(BusinessQaService::class)->answer('我有多少待审批？', $keeper);
        $this->assertSame('pending_approvals', $approval['intent']);
        $this->assertStringContainsString('待我审批', $approval['answer']);
        $this->assertStringContainsString('单待处理', $approval['answer']);

        $operator = User::where('username', 'operator')->firstOrFail();
        $production = app(BusinessQaService::class)->answer('生产工单进度怎么样？', $operator);
        $this->assertSame('production_progress', $production['intent']);
        $this->assertStringContainsString('生产工单进度', $production['answer']);
        $this->assertStringContainsString('未完工工单', $production['answer']);
    }

    public function test_business_qa_denies_module_without_permission_and_falls_back_for_unknown_intent(): void
    {
        $keeper = User::where('username', 'wh-keeper')->firstOrFail();
        $result = app(BusinessQaService::class)->answer('本月销售额多少？', $keeper);

        $this->assertSame('denied', $result['intent']);
        $this->assertStringContainsString('没有「销售」查看权限', $result['answer']);
        $this->assertArrayNotHasKey('amount', $result['facts']);

        // 租户账号 + 未纳管模块：明确拒绝，避免借问答跨租户取数
        $tenant = \App\Models\System\Tenant::create(['code' => 'QA-T1', 'name' => '问答测试租户', 'status' => 'active']);
        $keeper->forceFill(['tenant_id' => $tenant->id])->save();
        $tenantResult = app(BusinessQaService::class)->answer('EL000003 还有多少库存？', $keeper);
        $this->assertSame('tenant_scope_missing', $tenantResult['intent']);
        $this->assertStringContainsString('尚未纳入租户隔离', $tenantResult['answer']);
        $this->assertArrayNotHasKey('available', $tenantResult['facts']);

        $this->actingAs($keeper);
        $response = $this->post(route('ai.chat.send'), ['content' => '帮我写一首关于秋天的诗']);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('业务问答暂未命中')
            ->assertSee('本月销售额多少？');
    }
}
