<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiProvider;
use App\Services\BusinessQaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    public function __construct(private BusinessQaService $qa)
    {
    }

    public function index(Request $request)
    {
        $conversations = AiConversation::where('user_id', Auth::id())
            ->orderByDesc('id')->paginate(10, ['*'], 'p');

        $selected = null;
        if ($request->filled('c')) {
            $selected = AiConversation::where('user_id', Auth::id())->find((int) $request->input('c'));
        }
        $selected ??= $conversations->first();
        $messages = $selected ? $selected->messages()->get() : collect();
        $examples = $this->qa->examples();

        return view('ai.chat', compact('conversations', 'selected', 'messages', 'examples'));
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
        ]);

        $conv = null;
        if (! empty($data['conversation_id'])) {
            $conv = AiConversation::where('user_id', Auth::id())->find($data['conversation_id']);
        }
        $conv ??= AiConversation::create([
            'title' => mb_substr($data['content'], 0, 20),
            'user_id' => Auth::id(),
            'conversation_type' => 'chat',
        ]);

        AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'user',
            'content' => $data['content'],
        ]);

        $result = $this->qa->answer($data['content'], $request->user());
        $content = $result['answer'] ?? $this->fallback($data['content']);

        AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'assistant',
            'content' => $content,
        ]);

        return redirect()->route('ai.chat', ['c' => $conv->id]);
    }

    private function fallback(string $question): string
    {
        return "【业务问答暂未命中】已收到：" . $question . "\n"
            . "当前可直接回答的业务问题示例：\n- " . implode("\n- ", $this->qa->examples())
            . "\n其他开放问题需在 AI 服务商配置中接入 OpenAI/DeepSeek 后启用真实对话。";
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
        $provider->status = $request->boolean('status');
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

    /* ---------- OCR 发票识别（占位） ---------- */
    public function ocr()
    {
        return view('ai.ocr');
    }
}
