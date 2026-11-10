@extends('layouts.admin')

@section('title', '账号安全 / 二次验证')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
        @endif

        @if ($enabled)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>二次验证（TOTP）</span>
                    <span class="badge text-bg-success">已开启</span>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-3">
                        登录时除密码外还需输入认证器 App 的 6 位动态码。开启时间：{{ $user->mfa_enabled_at?->format('Y-m-d H:i') }}；
                        最近验证：{{ $user->mfa_last_verified_at?->format('Y-m-d H:i') ?? '-' }}。
                    </div>

                    @if (! empty($recoveryPlain))
                        <div class="alert alert-warning">
                            <div class="fw-semibold mb-2">恢复码（只显示这一次，请抄下保存）</div>
                            <div class="row g-1 font-monospace">
                                @foreach ($recoveryPlain as $code)
                                    <div class="col-6 col-md-3">{{ $code }}</div>
                                @endforeach
                            </div>
                            <div class="small mt-2">每个恢复码只能用一次；丢失后只能关闭重开。</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('mfa.disable') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small">登录密码</label>
                            <input type="password" name="password" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">当前动态码</label>
                            <input name="code" inputmode="numeric" maxlength="6" class="form-control form-control-sm" placeholder="6 位数字" required>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-outline-danger">关闭二次验证</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header">开启二次验证（TOTP）</div>
                <div class="card-body">
                    <ol class="small text-muted mb-3">
                        <li>在手机认证器 App（Microsoft Authenticator / Google Authenticator / 1Password 等）选择"添加账户 → 输入设置密钥"。</li>
                        <li>账户名填 <span class="font-monospace">{{ auth()->user()->username }}</span>，密钥填下面的字符串，类型选"基于时间"。若 App 支持扫码，也可直接导入下方 otpauth 链接。</li>
                        <li>输入 App 显示的 6 位动态码完成绑定。</li>
                    </ol>

                    <div class="mb-3">
                        <div class="small text-muted">设置密钥（Base32）</div>
                        <div class="font-monospace fs-5 user-select-all border rounded px-3 py-2 bg-light">{{ $secret }}</div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">otpauth 链接（可复制到支持链接导入的 App）</div>
                        <div class="font-monospace small text-break border rounded px-3 py-2 bg-light">{{ $uri }}</div>
                    </div>

                    <form method="POST" action="{{ route('mfa.enable') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small">App 显示的 6 位动态码</label>
                            <input name="code" inputmode="numeric" maxlength="6" class="form-control form-control-sm" placeholder="如 123456" required>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-sm btn-primary">验证并开启</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer small text-muted">
                    开启后会生成 8 个一次性恢复码；恢复码是手机丢失时唯一的自助入口。
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
