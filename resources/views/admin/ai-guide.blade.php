@extends('layouts.admin')

@section('title', 'AI 开通与设置')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">已接入的 AI 提供方（{{ $providers->count() }}）</div>
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>名称</th><th>编码</th><th>模型</th><th>API 地址</th><th class="text-end">状态</th></tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td>{{ $provider->name }}</td>
                            <td class="font-monospace small">{{ $provider->provider_code }}</td>
                            <td class="small">{{ $provider->model }}</td>
                            <td class="small text-muted text-truncate" style="max-width:220px;">{{ $provider->api_base }}</td>
                            <td class="text-end">
                                <span class="badge {{ $provider->status ? 'bg-success' : 'bg-secondary' }}">{{ $provider->status ? '启用' : '停用' }}</span>
                                <form method="POST" action="{{ route('ai.guide.providers.toggle', $provider) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-link">{{ $provider->status ? '停用' : '启用' }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">尚未接入任何 AI 提供方</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card mt-3">
            <div class="card-header">开通引导（三步）</div>
            <div class="card-body small">
                <ol class="ps-3 mb-0">
                    <li>在提供方控制台申请 API Key（企业账号建议单独建子账号，便于按量核算）。</li>
                    <li>右侧填写名称、编码（如 `dashscope` / `deepseek`）、API 地址与模型名，粘贴 Key 保存。</li>
                    <li>保存后状态为"启用"，AI 助手与各页面的智能提示即可调用；停用即断开，不影响其他模块。</li>
                </ol>
                <div class="text-muted mt-2">Key 以加密列存储（`api_key` encrypted），页面不回显明文。</div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">新增提供方</div>
            <form method="POST" action="{{ route('ai.guide.providers.store') }}" class="card-body">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-1">显示名称</label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="64" placeholder="通义千问">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">编码</label>
                    <input type="text" name="provider_code" class="form-control form-control-sm" required maxlength="32" placeholder="dashscope">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">API 地址</label>
                    <input type="text" name="api_base" class="form-control form-control-sm" required maxlength="200" placeholder="https://dashscope.aliyuncs.com/compatible-mode/v1">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">模型</label>
                    <input type="text" name="model" class="form-control form-control-sm" maxlength="64" placeholder="qwen-plus">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">API Key</label>
                    <input type="password" name="api_key" class="form-control form-control-sm" required maxlength="200">
                </div>
                <button class="btn btn-primary btn-sm w-100">保存并启用</button>
            </form>
        </div>
    </div>
</div>
@endsection
