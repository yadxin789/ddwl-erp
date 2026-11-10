<?php

namespace App\Http\Controllers;

use App\Models\System\WecomConfig;
use App\Models\System\WecomMessageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WecomController extends Controller
{
    public function index()
    {
        $config = WecomConfig::first();
        $logs = WecomMessageLog::orderByDesc('id')->paginate(15);

        return view('wecom.index', compact('config', 'logs'));
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'corp_id' => ['required', 'string', 'max:128'],
            'agent_id' => ['nullable', 'string', 'max:64'],
            'app_secret' => ['nullable', 'string', 'max:256'],
            'token' => ['nullable', 'string', 'max:256'],
            'encoding_aes_key' => ['nullable', 'string', 'max:256'],
        ]);

        // 修复空指针：先取已有配置，再决定 create 还是 update
        $config = WecomConfig::first();

        $payload = [
            'corp_id' => $data['corp_id'],
            'agent_id' => $data['agent_id'] ?? null,
            'status' => true,
        ];
        // 敏感字段：仅当本次提交非空字符串时才覆盖，避免空表单把已存密钥清空
        foreach (['app_secret', 'token', 'encoding_aes_key'] as $secretField) {
            if (! empty($data[$secretField])) {
                $payload[$secretField] = $data[$secretField];
            }
        }

        $config = WecomConfig::updateOrCreate(['id' => $config?->id], $payload);

        Log::info('企业微信配置已保存', [
            'user_id' => auth()->id(),
            'config_id' => $config->id,
            'corp_id' => $config->corp_id,
        ]);

        return back()->with('success', '企业微信配置已保存。');
    }
}
