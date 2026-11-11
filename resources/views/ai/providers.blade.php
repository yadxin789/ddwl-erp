@extends('layouts.admin')

@section('title', 'AI服务商配置')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>AI 服务商配置</span>
        <a href="{{ route('ai.chat') }}" class="btn btn-sm btn-outline-secondary">返回对话</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('ai.providers.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="name" class="form-control form-control-sm" placeholder="名称" required></div>
            <div class="col-md-2"><input name="provider_code" class="form-control form-control-sm" placeholder="如 openai/deepseek" required></div>
            <div class="col-md-3"><input name="api_base" class="form-control form-control-sm" placeholder="https://api.openai.com/v1" required></div>
            <div class="col-md-2"><input name="api_key" class="form-control form-control-sm" placeholder="接口密钥" required></div>
            <div class="col-md-2"><input name="model" class="form-control form-control-sm" placeholder="gpt-4o-mini" required></div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">添加</button></div>
            <div class="col-12 small text-muted">
                接口地址需包含版本路径：OpenAI 填 <code>https://api.openai.com/v1</code>，DeepSeek 填 <code>https://api.deepseek.com/v1</code>。
                添加后默认启用；保存后点「测试」会真实请求一次 <code>/chat/completions</code>。
            </div>
        </form>
    </div>
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>名称</th><th>代码</th><th>接口地址</th><th>模型</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
            @forelse ($providers as $p)
            <tr>
                <td>{{ $p->name }}</td>
                <td><span class="badge bg-light text-dark">{{ $p->provider_code }}</span></td>
                <td class="small font-monospace">{{ $p->api_base }}</td>
                <td>{{ $p->model }}</td>
                <td>@if($p->status)<span class="badge bg-success">启用</span>@else<span class="badge bg-secondary">停用</span>@endif</td>
                <td>
                    <div class="d-flex gap-1">
                        <form method="POST" action="{{ route('ai.providers.test', $p) }}">
                            @csrf <button class="btn btn-sm btn-outline-primary py-0">测试</button>
                        </form>
                        <form method="POST" action="{{ route('ai.providers.toggle', $p) }}">
                            @csrf <button class="btn btn-sm btn-outline-warning py-0">{{ $p->status ? '停用' : '启用' }}</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">尚未配置 AI 服务商</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $providers->links() }}</div>
</div>
@endsection
