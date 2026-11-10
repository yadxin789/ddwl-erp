@extends('layouts.admin')

@section('title', '企业微信绑定')

@section('content')
<div class="card" style="max-width:760px;">
    <div class="card-header">企业微信绑定</div>
    <div class="card-body">
        <div class="mb-3">
            <span class="text-muted">当前账号：</span><strong>{{ $user->name }}</strong>
            <span class="text-muted ms-3">绑定状态：</span>
            @if ($bound)
                <span class="badge bg-success">企业微信已接入</span>
            @else
                <span class="badge bg-secondary">未接入</span>
            @endif
        </div>

        @if (! $bound)
            <div class="alert alert-light border small mb-3">
                企业微信接入尚未由运维配置（corp_id / agent_id / secret）。
                配置完成后，此处会显示"扫码绑定"按钮，扫码后消息通知、审批提醒会推送到企业微信。
            </div>
        @endif

        <ol class="small text-muted ps-3">
            <li>由系统管理员在「AI 开通与设置 / 企业微信」中填写企业 corp_id、应用 agent_id 与 secret。</li>
            <li>运维启用后，本页出现「扫码绑定」入口，用企业微信扫码完成个人绑定。</li>
            <li>绑定后：我的待办、审批结果、库存预警通过企业微信推送；解绑在本页操作。</li>
        </ol>

        <div class="mt-3">
            <button class="btn btn-outline-primary" disabled>扫码绑定（待运维启用）</button>
            <a href="{{ route('office.profile') }}" class="btn btn-link">返回个人资料</a>
        </div>
    </div>
</div>
@endsection
