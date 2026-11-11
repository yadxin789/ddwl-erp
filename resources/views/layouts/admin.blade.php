<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DDWL-ERP 电子厂管理系统')</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/v4-shims.min.css') }}">
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
            <div class="dropdown user-info">
                @php($currentUser = auth()->user())
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                    <span class="avatar">
                        @if ($currentUser->avatar_url)
                            <img src="{{ $currentUser->avatar_url }}" alt="头像">
                        @else
                            {{ mb_substr($currentUser->name, 0, 1) }}
                        @endif
                    </span>
                    <span class="d-none d-md-inline">{{ $currentUser->identity_label }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <div class="dropdown-user-head">
                            <span class="avatar avatar-lg">
                                @if ($currentUser->avatar_url)
                                    <img src="{{ $currentUser->avatar_url }}" alt="头像">
                                @else
                                    {{ mb_substr($currentUser->name, 0, 1) }}
                                @endif
                            </span>
                            <span class="user-meta">
                                <span class="d-block fw-semibold">{{ $currentUser->identity_label }}</span>
                                <span class="d-block small text-muted">
                                    {{ $currentUser->position_label ?: ($currentUser->department->dept_name ?? '未分配岗位') }}
                                </span>
                            </span>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="{{ route('office.profile') }}">
                            <i class="fa-solid fa-id-card me-2"></i>基本资料
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('office.password') }}">
                            <i class="fa-solid fa-key me-2"></i>修改密码
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('office.avatar') }}">
                            <i class="fa-solid fa-image me-2"></i>上传头像
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
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

<script src="{{ asset('js/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
@yield('scripts')
</body>
</html>
