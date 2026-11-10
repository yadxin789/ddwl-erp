@extends('layouts.admin')

@section('title', '二次验证')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">需要二次验证</div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    账号 <span class="font-monospace">{{ auth()->user()->username }}</span> 已开启二次验证。
                    请输入认证器 App 的 6 位动态码，或使用一个恢复码。
                </div>
                @if ($errors->any())
                    <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('mfa.verify.submit') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">动态码 / 恢复码</label>
                        <input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20"
                               class="form-control" placeholder="6 位数字，或 XXXX-XXXX" autofocus required>
                    </div>
                    <button class="btn btn-primary w-100">验证</button>
                </form>
                <div class="small text-muted mt-3">
                    恢复码使用后立即作废。验证失败 5 次将锁定 1 分钟。
                </div>
            </div>
            <div class="card-footer text-end">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-link text-muted">退出登录</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
