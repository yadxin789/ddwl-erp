<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DDWL-ERP 电子厂管理系统')</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erp.css') }}">
</head>
<body>
<div class="app-wrapper">
    <!-- 深色侧边栏 -->
    <aside class="sidebar" id="appSidebar">
        <div class="brand">
            <i class="fa-solid fa-industry"></i>
            <span>DDWL-ERP</span>
        </div>
        @include('partials.sidebar')
    </aside>

    <!-- 主区域 -->
    <div class="main-content">
        <header class="topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-light d-lg-none" type="button" onclick="document.getElementById('appSidebar').classList.toggle('show')">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <span class="page-title">@yield('title', '工作台')</span>
            </div>
            <div class="dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                    <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <span class="dropdown-item-text small text-muted">
                            {{ auth()->user()->email ?? auth()->user()->username }}
                        </span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('mfa.setup') }}">
                            <i class="fa-solid fa-shield-halved me-2"></i>账号安全 / 二次验证
                        </a>
                    </li>
                    <li>
                        <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#changePwdModal">
                            <i class="fa-solid fa-key me-2"></i>修改密码
                        </button>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fa-solid fa-right-from-bracket me-2"></i>退出登录
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="content">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<!-- 修改密码 Modal -->
<div class="modal fade" id="changePwdModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('password.update') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">修改密码</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">当前密码</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">新密码（至少6位）</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">确认新密码</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
