@extends('layouts.admin')

@section('title', '企业微信集成')

@section('content')
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">企业微信配置</div>
            <div class="card-body">
                <form method="POST" action="{{ route('wecom.config') }}">
                    @csrf
                    <div class="mb-3"><label class="form-label">CorpID</label><input name="corp_id" class="form-control" value="{{ $config->corp_id ?? '' }}" required></div>
                    <div class="mb-3"><label class="form-label">AgentID</label><input name="agent_id" class="form-control" value="{{ $config->agent_id ?? '' }}"></div>
                    <div class="mb-3"><label class="form-label">AppSecret</label><input name="app_secret" type="password" class="form-control" value="{{ $config->app_secret ?? '' }}"></div>
                    <button class="btn btn-primary">保存配置</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">消息推送日志</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>接收人</th><th>类型</th><th>状态</th><th>时间</th></tr></thead>
                <tbody>
                    @forelse ($logs as $l)
                    <tr><td>{{ $l->to_user }}</td><td>{{ $l->msg_type }}</td><td>{{ \App\Enums\WecomMessageStatus::tryFrom($l->status)?->label() ?? $l->status }}</td><td class="small">{{ $l->sent_at?->format('m-d H:i') }}</td></tr>
                    @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">暂无推送记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
