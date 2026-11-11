<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册账号 - DDWL-ERP 电子厂管理系统</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <style>
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            font-size: .9rem;
            padding: 24px 0;
        }
        .register-card {
            width: 100%; max-width: 440px;
            border: none; border-radius: .75rem;
            box-shadow: 0 20px 45px rgba(0,0,0,.35);
        }
        .register-card .brand-icon {
            width: 56px; height: 56px; border-radius: 14px;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff; font-size: 1.5rem;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }
        .form-control { padding: .65rem .9rem; }
        .btn-primary {
            background: #2563eb; border-color: #2563eb;
            padding: .65rem 1rem; font-weight: 600;
        }
    </style>
</head>
<body>
<div class="card register-card">
    <div class="card-body p-4 p-md-5">
        <div class="brand-icon"><i class="fa-solid fa-user-plus"></i></div>
        <h4 class="text-center fw-bold mb-1">注册账号</h4>
        <p class="text-center text-muted mb-4">注册后需管理员审核并分配岗位</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <div class="alert alert-warning py-2 small">
            <i class="fa-solid fa-circle-info me-1"></i>
            忘记密码无法自助找回：请联系系统管理员注销旧账号后，再在此重新注册。
        </div>

        <form method="POST" action="{{ route('register.submit') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">登录账号 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}"
                           required maxlength="32" autofocus autocomplete="username" placeholder="用于登录，建议使用姓名">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">姓名 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-id-card"></i></span>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="64">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">邮箱</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="128">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">密码 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">确认密码 <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-paper-plane me-2"></i>提交注册申请
            </button>
        </form>

        <div class="text-center mt-3 small">
            已有账号？<a href="{{ route('login') }}">返回登录</a>
        </div>
    </div>
</div>
</body>
</html>
