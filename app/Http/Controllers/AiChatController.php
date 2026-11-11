<?php

namespace App\Http\Controllers;

use App\Ai\ToolRegistry;
use App\Models\Ai\AiAttachment;
use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiSkill;
use App\Services\AiService;
use App\Services\BusinessQaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    public function __construct(
        private BusinessQaService $qa,
        private AiService $ai,
        private ToolRegistry $tools,
    ) {
    }

    public function index(Request $request)
    {
        $userId = Auth::id();

        $conversations = AiConversation::where('user_id', $userId)
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $selected = null;
        if ($request->filled('c')) {
            $selected = AiConversation::where('user_id', $userId)
                ->with(['messages.attachments'])
                ->find((int) $request->input('c'));
        }
        $selected ??= $conversations->first();
        if ($selected && ! $selected->relationLoaded('messages')) {
            $selected->load(['messages.attachments']);
        }

        $messages = $selected?->messages ?? collect();
        $providers = AiProvider::where('status', true)->orderBy('name')->get();
        $skills = AiSkill::where('enabled', true)->orderBy('id')->get();
        $examples = $this->qa->examples();
        $promptTips = $this->promptTips();

        return view('ai.chat', compact(
            'conversations', 'selected', 'messages', 'providers', 'skills', 'examples', 'promptTips'
        ));
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
            'provider_id' => ['nullable', 'integer'],
            'skill_code' => ['nullable', 'string', 'max:64', 'exists:ai_skills,code'],
            'images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,bmp,webp', 'max:10240'],
            'files.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,xlsm,ods,ppt,pptx,zip,txt,md,csv,json,xml,log,yaml,yml,ini,sql,html,htm,odt', 'max:20480'],
        ]);

        $content = trim((string) ($data['content'] ?? ''));
        $hasUpload = $request->hasFile('images') || $request->hasFile('files');
        if ($content === '' && ! $hasUpload) {
            return back()->withInput()->withErrors(['content' => '请输入问题，或上传图片 / 文件后再发送。']);
        }

        $conv = null;
        if (! empty($data['conversation_id'])) {
            $conv = AiConversation::where('user_id', Auth::id())->find($data['conversation_id']);
            abort_unless($conv, 404);
        }
        $conv ??= AiConversation::create([
            'title' => '新会话',
            'user_id' => Auth::id(),
            'conversation_type' => 'chat',
            'last_message_at' => now(),
            'message_count' => 0,
        ]);

        $provider = $this->providerFromInput($data['provider_id'] ?? null);
        $skill = $this->skillFromInput($data['skill_code'] ?? null);

        $meta = [
            'provider_id' => $provider?->id,
            'provider_name' => $provider?->name,
            'model_name' => $provider?->model,
            'skill_code' => $skill?->code,
            'skill_name' => $skill?->name,
        ];

        $userMessage = AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'user',
            'content' => $content !== '' ? $content : '（仅上传附件）',
            'meta' => $meta,
        ]);

        $fileNames = [];
        foreach ($request->file('images', []) as $file) {
            $fileNames[] = $this->storeAttachment($file, $conv, $userMessage);
        }
        foreach ($request->file('files', []) as $file) {
            $fileNames[] = $this->storeAttachment($file, $conv, $userMessage);
        }

        $question = $content !== '' ? $content : '请阅读本轮上传的附件。';
        if ($fileNames) {
            $question .= "\n\n【本轮附件】" . implode('、', $fileNames);
        }

        $attachments = AiAttachment::where('message_id', $userMessage->id)->orderBy('id')->get();

        if ($provider) {
            $history = $conv->messages()
                ->where('id', '<', $userMessage->id)
                ->latest('id')
                ->limit(12)
                ->get()
                ->reverse()
                ->map(fn (AiMessage $m) => [
                    'role' => $m->role,
                    'content' => mb_substr((string) $m->content, 0, 4000),
                ])
                ->values()
                ->all();

            $names = (array) data_get($skill?->config, 'tools', []);
            $selectedTools = $skill ? $this->tools->forNames($names, true) : $this->tools->all();

            $aiResult = $this->ai->chatWithTools(
                $request->user(),
                $provider,
                $question,
                $history,
                $selectedTools,
                $this->skillInstructions($skill),
                $attachments,
            );

            $answer = $aiResult['content'];
            $meta['tools_used'] = $aiResult['tools'];
            $meta['usage'] = $aiResult['usage'];
            $meta['ai_error'] = $aiResult['error'];
        } else {
            $result = $this->qa->answer($question, $request->user());
            $answer = $result['answer'] ?? $this->fallback($content !== '' ? $content : implode('、', $fileNames));
        }

        AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'assistant',
            'content' => $answer,
            'meta' => $meta,
        ]);

        $title = $conv->title;
        if (! $title || $title === '新会话') {
            $title = $content !== ''
                ? mb_substr($content, 0, 24)
                : '附件对话 ' . now()->format('m-d H:i');
        }

        $conv->update([
            'title' => $title,
            'provider_id' => $provider?->id,
            'skill_code' => $skill?->code,
            'message_count' => $conv->messages()->count(),
            'last_message_at' => now(),
        ]);

        return redirect()->route('ai.chat', ['c' => $conv->id])->with('success', '对话已保存。');
    }

    /* ---------- 会话管理 ---------- */

    public function storeConversation(Request $request)
    {
        $data = $request->validate([
            'provider_id' => ['nullable', 'integer'],
            'skill_code' => ['nullable', 'string', 'max:64', 'exists:ai_skills,code'],
        ]);

        $conv = AiConversation::create([
            'title' => '新会话',
            'user_id' => Auth::id(),
            'provider_id' => $this->providerFromInput($data['provider_id'] ?? null)?->id
                ?? AiProvider::where('status', true)->value('id'),
            'skill_code' => $this->skillFromInput($data['skill_code'] ?? null)?->code,
            'conversation_type' => 'chat',
            'message_count' => 0,
            'last_message_at' => now(),
        ]);

        return redirect()->route('ai.chat', ['c' => $conv->id]);
    }

    public function updateConversation(Request $request, AiConversation $conversation)
    {
        $this->assertOwner($conversation);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'provider_id' => ['nullable', 'integer'],
            'skill_code' => ['nullable', 'string', 'max:64', 'exists:ai_skills,code'],
        ]);

        $patch = [];
        if (! empty($data['title'])) {
            $patch['title'] = $data['title'];
        }
        if ($request->exists('provider_id')) {
            $patch['provider_id'] = $this->providerFromInput($data['provider_id'] ?? null)?->id;
        }
        if ($request->exists('skill_code')) {
            $patch['skill_code'] = $this->skillFromInput($data['skill_code'] ?? null)?->code;
        }
        if ($patch) {
            $conversation->update($patch);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'title' => $conversation->title,
                'provider_id' => $conversation->provider_id,
                'skill_code' => $conversation->skill_code,
            ]);
        }

        return back()->with('success', '会话设置已更新。');
    }

    public function destroyConversation(AiConversation $conversation)
    {
        $this->assertOwner($conversation);
        $this->deleteConversationFiles($conversation);
        $conversation->messages()->delete();
        $conversation->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('ai.chat')->with('success', '会话及附件已删除。');
    }

    public function clearConversation(AiConversation $conversation)
    {
        $this->assertOwner($conversation);
        $this->deleteConversationFiles($conversation);
        $conversation->messages()->delete();
        $conversation->update(['message_count' => 0, 'last_message_at' => now()]);

        return back()->with('success', '当前会话消息与附件已清空。');
    }

    public function exportConversation(AiConversation $conversation): StreamedResponse
    {
        $this->assertOwner($conversation);
        $conversation->load(['messages.attachments']);

        $lines = [
            '# ' . ($conversation->title ?: 'AI 对话'),
            '',
            '> 导出时间：' . now()->format('Y-m-d H:i:s'),
            '',
        ];
        foreach ($conversation->messages as $message) {
            $lines[] = '## ' . ($message->role === 'user' ? '👤 我' : '🤖 AI 助手');
            $lines[] = '';
            foreach ($message->attachments as $attachment) {
                $lines[] = '- 附件：' . $attachment->file_name;
            }
            if ($message->attachments->isNotEmpty()) {
                $lines[] = '';
            }
            $lines[] = (string) $message->content;
            $lines[] = '';
        }

        return response()->streamDownload(function () use ($lines) {
            echo implode("\n", $lines);
        }, 'ai-chat-' . $conversation->id . '.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /* ---------- 附件 ---------- */

    public function previewAttachment(AiAttachment $attachment)
    {
        $this->assertAttachmentOwner($attachment);
        abort_unless($attachment->isImage(), 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response($attachment->file_path, $attachment->file_name, [], 'inline');
    }

    public function downloadAttachment(AiAttachment $attachment)
    {
        $this->assertAttachmentOwner($attachment);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }

    /* ---------- 私有方法 ---------- */

    private function storeAttachment($file, AiConversation $conversation, AiMessage $message): string
    {
        $path = $file->store('ai_attachments/' . $conversation->id, 'local');
        $name = $file->getClientOriginalName();

        AiAttachment::create([
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'file_name' => $name,
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return $name;
    }

    private function deleteConversationFiles(AiConversation $conversation): void
    {
        $conversation->loadMissing('messages.attachments');
        $attachments = AiAttachment::where('conversation_id', $conversation->id)->get()
            ->merge($conversation->messages->flatMap->attachments)
            ->unique('id');

        foreach ($attachments as $attachment) {
            if ($attachment->file_path && Storage::disk('local')->exists($attachment->file_path)) {
                Storage::disk('local')->delete($attachment->file_path);
            }
            $attachment->delete();
        }
    }

    private function assertOwner(AiConversation $conversation): void
    {
        abort_unless((int) $conversation->user_id === (int) Auth::id(), 403);
    }

    private function assertAttachmentOwner(AiAttachment $attachment): void
    {
        $ownerId = $attachment->conversation?->user_id ?? $attachment->message?->conversation?->user_id;
        abort_unless((int) $ownerId === (int) Auth::id(), 403);
    }

    private function providerFromInput($providerId): ?AiProvider
    {
        if ($providerId === null || $providerId === '' || $providerId === 'builtin') {
            return null;
        }

        return AiProvider::where('status', true)->find((int) $providerId);
    }

    private function skillFromInput(?string $code): ?AiSkill
    {
        if (! $code) {
            return null;
        }

        return AiSkill::where('code', $code)->where('enabled', true)->first();
    }

    private function promptTips(): array
    {
        return [
            ['label' => '加上时间范围', 'text' => '本月销售额多少？'],
            ['label' => '带上物料编码', 'text' => 'ACC-RM01 还有多少库存？'],
            ['label' => '明确审批口径', 'text' => '我有多少待审批？'],
            ['label' => '限定工单范围', 'text' => '生产工单进度怎么样？'],
        ];
    }

    private function fallback(string $question): string
    {
        return "【业务问答暂未命中】已收到：" . $question . "\n"
            . "可直接回答的业务问题示例：\n- " . implode("\n- ", $this->qa->examples())
            . "\n如需开放式问答和工具调用，请在上方模型下拉中选择已启用的 AI 服务商。";
    }

    private function skillInstructions(?AiSkill $skill): ?string
    {
        if (! $skill) {
            return null;
        }

        $names = (array) data_get($skill->config, 'tools', []);
        $instructions = "当前选择的技能：{$skill->name}（类型 {$skill->type}）。\n{$skill->description}\n";
        if ($names !== []) {
            $instructions .= '该技能登记的工具：' . implode('、', $names) . "。\n";
        }

        if ($skill->type === 'doc-generator') {
            $instructions .= "请优先使用知识库、项目、任务等查询结果，按用户要求生成结构完整的 Markdown 文档；未知数据标注【待补充】，不得编造。\n";
        }
        if ($skill->type === 'agent' || $skill->code === 'data-change') {
            $instructions .= "当前系统只开放只读查询工具，没有变更提案工具。不得声称已修改任何业务数据；用户要求变更时，请说明应到哪个业务页面操作。\n";
        }

        return $instructions;
    }

    /* ---------- 服务商配置（管理员） ---------- */

    public function providers()
    {
        $providers = AiProvider::orderByDesc('id')->paginate(15);

        return view('ai.providers', compact('providers'));
    }

    public function providerStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'provider_code' => ['required', 'string', 'max:32'],
            'api_base' => ['required', 'url', 'max:255'],
            'api_key' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:64'],
        ]);

        // api_key 不在 fillable 中（敏感凭据），显式赋值并加密落库
        $provider = new AiProvider();
        $provider->name = $data['name'];
        $provider->provider_code = $data['provider_code'];
        $provider->api_base = $data['api_base'];
        $provider->model = $data['model'];
        $provider->status = $request->boolean('status', true);
        $provider->api_key = $data['api_key'];
        $provider->save();

        \Illuminate\Support\Facades\Log::info('AI服务商配置已创建', [
            'user_id' => Auth::id(),
            'provider_id' => $provider->id,
            'provider_code' => $provider->provider_code,
        ]);

        return back()->with('success', 'AI服务商已配置。');
    }

    public function providerToggle(AiProvider $provider)
    {
        $provider->update(['status' => ! $provider->status]);

        return back()->with('success', $provider->status ? '已启用' : '已停用');
    }

    public function providerTest(AiProvider $provider)
    {
        $result = $this->ai->testConnection($provider);

        if ($result['ok']) {
            return back()->with('success', $result['message']);
        }

        return back()->withErrors(['provider_test' => $result['message']]);
    }

    /* ---------- OCR 发票识别（占位） ---------- */

    public function ocr()
    {
        return view('ai.ocr');
    }
}
