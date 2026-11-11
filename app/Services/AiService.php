<?php

namespace App\Services;

use App\Ai\ToolRegistry;
use App\Models\Ai\AiAttachment;
use App\Models\Ai\AiProvider;
use App\Models\Organization\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 真实大模型服务：OpenAI 兼容 Chat Completions + Function Calling。
 *
 * 参考项目 pm-server-openEuler 的 AiService 设计，但本系统不引入 laravel/ai 包，
 * 直接用已有的 Guzzle/Http 客户端调用 DeepSeek / OpenAI / 其他 OpenAI 兼容网关。
 *
 * 只负责协议循环；权限与数据范围由 ToolRegistry 中每个工具自行校验。
 */
class AiService
{
    private const MAX_TOOL_ROUNDS = 6;

    public function __construct(private readonly ToolRegistry $registry)
    {
    }

    public function isAvailable(AiProvider $provider): bool
    {
        return $provider->status && $provider->api_base !== '' && $provider->api_key !== '' && $provider->model !== '';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<string, \App\Ai\Contracts\AiTool>  $tools
     * @param  iterable<AiAttachment>  $attachments
     * @return array{content: string, tools: array<int, array<string, mixed>>, usage: array<string, mixed>, error: bool}
     */
    public function chatWithTools(
        User $user,
        AiProvider $provider,
        string $message,
        array $history = [],
        array $tools = [],
        ?string $extraInstructions = null,
        iterable $attachments = [],
    ): array {
        if (! $this->isAvailable($provider)) {
            return $this->error('AI 服务商未启用或配置不完整。');
        }

        $tools = $tools ?: $this->registry->all();
        $definitions = $this->registry->definitions($tools);

        [$userContent, $attachmentNotes] = $this->buildUserContent($provider, $message, $attachments);
        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt($user, $tools, $extraInstructions, $attachmentNotes)],
        ];
        foreach ($history as $item) {
            $role = (string) ($item['role'] ?? '');
            $content = (string) ($item['content'] ?? '');
            if ($role === 'user' || $role === 'assistant') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $userContent];

        $toolTrace = [];
        $usage = [];

        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $response = $this->request($provider, $messages, $definitions);
            if (! $response['ok']) {
                return [
                    'content' => $response['message'],
                    'tools' => $toolTrace,
                    'usage' => $usage,
                    'error' => true,
                ];
            }

            $body = $response['body'];
            $usage = is_array($body['usage'] ?? null) ? $body['usage'] : $usage;
            $assistant = $body['choices'][0]['message'] ?? null;
            if (! is_array($assistant)) {
                return $this->error('AI 返回格式异常：缺少 choices[0].message。');
            }

            $toolCalls = $assistant['tool_calls'] ?? [];
            if (! is_array($toolCalls) || $toolCalls === []) {
                $content = trim((string) ($assistant['content'] ?? ''));
                if ($content === '') {
                    return $this->error('AI 未返回内容，请稍后重试。');
                }

                return ['content' => $content, 'tools' => $toolTrace, 'usage' => $usage, 'error' => false];
            }

            // 必须原样回传 assistant.tool_calls，部分兼容网关要求 id / function 保持一致。
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistant['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $id = (string) ($call['id'] ?? '');
                $name = (string) data_get($call, 'function.name', '');
                $rawArgs = data_get($call, 'function.arguments', '{}');
                $args = is_array($rawArgs) ? $rawArgs : json_decode((string) $rawArgs, true);
                if (! is_array($args)) {
                    $args = [];
                }

                if ($id === '' || $name === '') {
                    continue;
                }

                $result = $this->registry->execute($name, $args, $user);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $id,
                    'content' => $result,
                ];
                $toolTrace[] = [
                    'name' => $name,
                    'arguments' => $args,
                    'result' => mb_substr($result, 0, 500),
                ];
            }
        }

        return $this->error('工具调用轮次超过 ' . self::MAX_TOOL_ROUNDS . ' 次，已停止。请缩小问题范围后重试。');
    }

    public function testConnection(AiProvider $provider): array
    {
        if (! $this->isAvailable($provider)) {
            return ['ok' => false, 'message' => '服务商未启用或 Base URL / API Key / 模型未填写完整。'];
        }

        try {
            $response = $this->client($provider, 30)
                ->post($this->chatUrl($provider), [
                    'model' => $provider->model,
                    'messages' => [
                        ['role' => 'system', 'content' => '你是连接测试助手。'],
                        ['role' => 'user', 'content' => '请只回复四个字：连接成功'],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 32,
                ]);

            if ($response->failed()) {
                return ['ok' => false, 'message' => '连接失败（HTTP ' . $response->status() . '）：' . $this->shortBody($response->body())];
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

            return ['ok' => true, 'message' => '连接成功，模型回复：' . mb_substr($content ?: '（空）', 0, 80)];
        } catch (\Throwable $e) {
            Log::warning('AI 连接测试异常', ['provider_id' => $provider->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => '连接异常：' . $e->getMessage()];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $definitions
     * @return array{ok: bool, body?: array<string, mixed>, message?: string}
     */
    private function request(AiProvider $provider, array $messages, array $definitions): array
    {
        $payload = [
            'model' => $provider->model,
            'messages' => $messages,
            'temperature' => 0.2,
            'max_tokens' => 1600,
        ];
        if ($definitions !== []) {
            $payload['tools'] = $definitions;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = $this->client($provider, 120)
                ->post($this->chatUrl($provider), $payload);
        } catch (\Throwable $e) {
            Log::warning('AI 请求异常', ['provider_id' => $provider->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'AI 服务连接异常：' . $e->getMessage()];
        }

        if ($response->failed()) {
            Log::warning('AI 接口返回错误', [
                'provider_id' => $provider->id,
                'status' => $response->status(),
                'body' => $this->shortBody($response->body()),
            ]);

            return [
                'ok' => false,
                'message' => 'AI 服务返回错误（HTTP ' . $response->status() . '）：' . $this->shortBody($response->body()),
            ];
        }

        $body = $response->json();
        if (! is_array($body)) {
            return ['ok' => false, 'message' => 'AI 返回不是合法 JSON。'];
        }

        return ['ok' => true, 'body' => $body];
    }

    private function client(AiProvider $provider, int $timeout): PendingRequest
    {
        $client = Http::acceptJson()->timeout($timeout);

        if (str_contains(strtolower((string) $provider->provider_code), 'azure')) {
            return $client->withHeaders(['api-key' => (string) $provider->api_key]);
        }

        return $client->withToken((string) $provider->api_key);
    }

    private function chatUrl(AiProvider $provider): string
    {
        $base = rtrim((string) $provider->api_base, '/');
        if (str_ends_with($base, '/chat/completions')) {
            return $base;
        }

        return $base . '/chat/completions';
    }

    private function systemPrompt(
        User $user,
        array $tools,
        ?string $extraInstructions,
        array $attachmentNotes,
    ): string {
        $names = array_keys($tools);
        $dept = $user->department?->dept_name ?? '—';
        $position = $user->position?->pos_name ?? '—';

        $prompt = "你是 DW-ERP 企业资源管理系统的 AI 助手，当前登录用户是「{$user->name}」（部门：{$dept}，岗位：{$position}）。\n"
            . "今天是 " . now()->format('Y-m-d') . "。\n\n"
            . "【工具使用规则】\n"
            . "1. 任何业务数字、单据、项目、任务、库存、审批、客户、知识库资料，都必须先调用工具查询；禁止编造。\n"
            . "2. 工具返回“没有权限”时如实转达，禁止绕过权限。工具返回“没有数据”时说明查询口径，并建议用户换关键词。\n"
            . "3. 当前系统只开放只读查询工具。你绝对不能声称已新增、修改、删除、审批或过账任何业务数据；用户要求变更时，请引导到对应业务页面操作。\n"
            . "4. 回答使用简洁中文，先给结论，再列依据和口径（时间范围、数据范围）。\n"
            . "5. 不要输出 API Key、密码、Token、手机号、邮箱、地址等敏感信息。\n"
            . ($names !== [] ? "6. 本次可用工具：" . implode('、', $names) . "。\n" : "6. 本次没有可用工具，请基于用户提供的信息回答，并说明无法查询系统数据。\n")
            . "\n【数据范围】所有工具都按当前账号的权限和行级数据范围过滤，你看到的数据已经是该账号可见范围。\n";

        if ($attachmentNotes !== []) {
            $prompt .= "\n【本轮附件】\n" . implode("\n", $attachmentNotes) . "\n";
        }

        if ($extraInstructions) {
            $prompt .= "\n【本次技能要求】\n" . $extraInstructions . "\n";
        }

        return $prompt;
    }

    /**
     * @param  iterable<AiAttachment>  $attachments
     * @return array{0: string|array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function buildUserContent(AiProvider $provider, string $message, iterable $attachments): array
    {
        $notes = [];
        $text = $message;
        $imageParts = [];

        foreach ($attachments as $attachment) {
            if (! $attachment instanceof AiAttachment) {
                continue;
            }

            if ($attachment->isImage()) {
                if ($this->supportsVision($provider)) {
                    $absolute = Storage::disk('local')->path($attachment->file_path);
                    $bytes = is_file($absolute) ? (int) filesize($absolute) : 0;
                    if ($bytes > 0 && $bytes <= 5 * 1024 * 1024) {
                        $mime = $attachment->mime_type ?: 'image/jpeg';
                        $imageParts[] = [
                            'type' => 'image_url',
                            'image_url' => ['url' => 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolute))],
                        ];
                        $notes[] = '- 图片：' . $attachment->file_name . '（已随消息发送给视觉模型）';
                    } elseif ($bytes > 5 * 1024 * 1024) {
                        $notes[] = '- 图片：' . $attachment->file_name . '（超过 5MB，未发送给视觉模型，请压缩后重试）';
                    } else {
                        $notes[] = '- 图片：' . $attachment->file_name . '（文件缺失，未能读取）';
                    }
                } else {
                    $notes[] = '- 图片：' . $attachment->file_name . '（当前模型未声明视觉能力，无法读取画面，请让用户用文字描述关键内容）';
                }
                continue;
            }

            $excerpt = $this->extractText($attachment);
            if ($excerpt !== '') {
                $text .= "\n\n【附件：{$attachment->file_name}】\n" . $excerpt;
                $notes[] = '- 文件：' . $attachment->file_name . '（已提取文本摘录 ' . mb_strlen($excerpt) . ' 字）';
            } else {
                $notes[] = '- 文件：' . $attachment->file_name . '（当前仅支持文本类与 DOCX 文本提取，未能读取正文）';
            }
        }

        if ($text === '') {
            $text = '请阅读本轮上传的附件。';
        }

        if ($imageParts !== []) {
            return [
                array_merge([['type' => 'text', 'text' => $text]], $imageParts),
                $notes,
            ];
        }

        return [$text, $notes];
    }

    private function supportsVision(AiProvider $provider): bool
    {
        $code = strtolower((string) $provider->provider_code);
        $model = strtolower((string) $provider->model);

        return in_array($code, ['openai', 'azure_openai', 'azure-openai'], true)
            || str_contains($model, 'gpt-4o')
            || str_contains($model, 'gpt-4.1')
            || str_contains($model, 'vision');
    }

    private function extractText(AiAttachment $attachment): string
    {
        $path = (string) $attachment->file_path;
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return '';
        }

        $ext = strtolower(pathinfo((string) $attachment->file_name, PATHINFO_EXTENSION));
        $absolute = Storage::disk('local')->path($path);

        if (in_array($ext, ['txt', 'md', 'csv', 'json', 'xml', 'log', 'yaml', 'yml', 'ini', 'sql'], true)) {
            $content = @file_get_contents($absolute);
            if ($content === false) {
                return '';
            }

            return mb_substr($content, 0, 8000);
        }

        if ($ext === 'docx' && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($absolute) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    $text = strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml));

                    return mb_substr(trim($text), 0, 8000);
                }
            }
        }

        return '';
    }

    private function shortBody(string $body): string
    {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $message = $decoded['error']['message'] ?? $decoded['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return mb_substr($message, 0, 300);
            }
        }

        return mb_substr(trim(strip_tags($body)), 0, 300);
    }

    /**
     * @return array{content: string, tools: array<int, array<string, mixed>>, usage: array<string, mixed>, error: bool}
     */
    private function error(string $message): array
    {
        return ['content' => $message, 'tools' => [], 'usage' => [], 'error' => true];
    }
}
