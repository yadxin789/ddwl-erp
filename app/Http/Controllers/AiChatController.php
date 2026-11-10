<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Models\Ai\AiProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiChatController extends Controller
{
    public function index()
    {
        $conversations = AiConversation::where('user_id', Auth::id())
            ->orderByDesc('id')->paginate(10, ['*'], 'c');

        return view('ai.chat', compact('conversations'));
    }

    public function send(Request $request)
    {
        $request->validate(['content' => ['required', 'string', 'max:4000']]);

        $conv = AiConversation::create([
            'title' => mb_substr($request->input('content'), 0, 20),
            'user_id' => Auth::id(),
            'conversation_type' => 'chat',
        ]);
        AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'user',
            'content' => $request->input('content'),
        ]);
        // 占位回复：真实环境在此调用 LLM API（guzzle）
        AiMessage::create([
            'conversation_id' => $conv->id,
            'role' => 'assistant',
            'content' => '【AI服务未配置】已收到您的消息：' . $request->input('content') . '。请在 AI 服务商配置中接入 OpenAI/DeepSeek 后启用真实对话。',
        ]);

        return back();
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
