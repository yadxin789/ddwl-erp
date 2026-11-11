<?php

namespace Tests\Feature;

use App\Models\Ai\AiMessage;
use App\Models\Ai\AiProvider;
use App\Models\Organization\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiToolCallingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_calls_provider_and_executes_tool_then_answers_from_result(): void
    {
        $user = User::where('username', '钱七')->firstOrFail();
        $provider = new AiProvider();
        $provider->name = '测试 DeepSeek';
        $provider->provider_code = 'deepseek';
        $provider->api_base = 'https://api.deepseek.test/v1';
        $provider->model = 'deepseek-chat';
        $provider->status = true;
        $provider->api_key = 'sk-test-key';
        $provider->save();

        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'id' => 'chatcmpl-1',
                    'choices' => [[
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [[
                                'id' => 'call_sales_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'QuerySalesOrders',
                                    'arguments' => '{"period":"month","limit":5}',
                                ],
                            ]],
                        ],
                        'finish_reason' => 'tool_calls',
                    ]],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
                ])
                ->push([
                    'id' => 'chatcmpl-2',
                    'choices' => [[
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '本月销售订单 1 张，金额 500.00 元。',
                        ],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 20],
                ]),
        ]);

        $response = $this->actingAs($user)->post(route('ai.chat.send'), [
            'content' => '本月销售额多少？',
            'provider_id' => $provider->id,
        ]);
        $response->assertRedirect();

        $assistant = AiMessage::where('role', 'assistant')->latest('id')->firstOrFail();
        $this->assertSame('本月销售订单 1 张，金额 500.00 元。', $assistant->content);
        $this->assertSame('QuerySalesOrders', $assistant->meta['tools_used'][0]['name']);
        $this->assertFalse($assistant->meta['ai_error']);

        // 第一次请求：带 tools，模型选择自动调用；第二次请求：带回 role=tool 的执行结果。
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return isset($request['tools'])
                && collect($request['tools'])->contains(fn ($tool) => $tool['function']['name'] === 'QuerySalesOrders');
        });
        Http::assertSent(function ($request) {
            return collect($request['messages'] ?? [])->contains(
                fn ($message) => ($message['role'] ?? '') === 'tool'
                    && str_contains((string) ($message['content'] ?? ''), 'SO-ACC-001')
            );
        });
    }

    public function test_tools_enforce_permissions_and_return_grounded_sales_data(): void
    {
        $sales = User::where('username', '钱七')->firstOrFail();
        $warehouse = User::where('username', '王五')->firstOrFail();
        $registry = app(\App\Ai\ToolRegistry::class);

        $this->actingAs($sales);
        $salesResult = $registry->execute('QuerySalesOrders', ['period' => 'month'], $sales);
        $this->assertStringContainsString('SO-ACC-001', $salesResult);

        $warehouseResult = $registry->execute('QuerySalesOrders', ['period' => 'month'], $warehouse);
        $this->assertStringContainsString('没有销售查看权限', $warehouseResult);

        $this->assertArrayHasKey('QueryInventory', $registry->all());
        $this->assertArrayHasKey('QueryPendingApprovals', $registry->all());
        $this->assertArrayHasKey('QueryProductionOrders', $registry->all());
    }

    public function test_text_attachment_content_is_sent_to_model(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $user = User::where('username', '钱七')->firstOrFail();
        $provider = new AiProvider();
        $provider->name = '测试 DeepSeek';
        $provider->provider_code = 'deepseek';
        $provider->api_base = 'https://api.deepseek.test/v1';
        $provider->model = 'deepseek-chat';
        $provider->status = true;
        $provider->api_key = 'sk-test-key';
        $provider->save();

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '已读取附件。']]],
            ]),
        ]);

        $this->actingAs($user)->post(route('ai.chat.send'), [
            'content' => '请总结附件',
            'provider_id' => $provider->id,
            'files' => [\Illuminate\Http\UploadedFile::fake()->createWithContent('说明.txt', '本月重点客户是验收客户')],
        ])->assertRedirect();

        Http::assertSent(function ($request) {
            return collect($request['messages'] ?? [])->contains(
                fn ($message) => str_contains((string) ($message['content'] ?? ''), '本月重点客户是验收客户')
            );
        });
    }

    public function test_provider_connection_test_uses_real_chat_endpoint(): void
    {
        $provider = new AiProvider();
        $provider->name = '测试 OpenAI';
        $provider->provider_code = 'openai';
        $provider->api_base = 'https://api.openai.test/v1';
        $provider->model = 'gpt-4o-mini';
        $provider->status = true;
        $provider->api_key = 'sk-test-key';
        $provider->save();

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '连接成功']]],
            ]),
        ]);

        $this->actingAs(User::where('username', '张三')->firstOrFail())
            ->post(route('ai.providers.test', $provider))
            ->assertRedirect();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions')
            && $request['messages'][1]['content'] === '请只回复四个字：连接成功');
    }
}
