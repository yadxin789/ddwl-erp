<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - DDWL-ERP 电子厂管理系统</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <style>
        body {
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            font-size: .9rem;
        }
        .login-card {
            width: 100%; max-width: 400px;
            border: none; border-radius: .75rem;
            box-shadow: 0 20px 45px rgba(0,0,0,.35);
        }
        .login-card .brand-icon {
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
        .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .demo-accounts {
            background: #f8fafc; border-radius: .5rem;
            font-size: .8rem; color: #64748b;
        }
    </style>
</head>
<body>
<div class="card login-card">
    <div class="card-body p-4 p-md-5">
        <div class="brand-icon"><i class="fa-solid fa-industry"></i></div>
        <h4 class="text-center fw-bold mb-1">DDWL-ERP</h4>
        <p class="text-center text-muted mb-4">电子厂管理系统</p>

        @if ($errors->any())
            <div class="alert alert-danger py-2">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">账号（用户名或邮箱）</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="account" class="form-control" value="{{ old('account') }}" required autofocus autocomplete="username">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">密码</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">记住我</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fa-solid fa-right-to-bracket me-2"></i>登 录
            </button>
        </form>

        <div class="demo-accounts p-3 mt-4">
            <div class="fw-semibold mb-2"><i class="fa-solid fa-circle-info me-1"></i>演示账号（密码均为 123456）</div>
            <div>管理员：admin</div>
            <div>仓库主管：wh-supervisor@demo.com</div>
            <div>采购专员：purchase@demo.com</div>
            <div>销售专员：sales@demo.com</div>
        </div>
    </div>
</div>
</body>
</html>
