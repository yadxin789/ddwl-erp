<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 · 该页面不属于您的岗位</title>
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/all.min.css') }}">
</head>
<body class="bg-light">
<div class="container" style="max-width: 640px; margin-top: 10vh;">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-3">
                <i class="fa-solid fa-lock text-danger" style="font-size: 2rem;"></i>
                <div>
                    <h4 class="mb-0">403 · 该页面不属于您的岗位</h4>
                    <small class="text-muted">页面守卫已拦截本次访问，并已记入安全审计</small>
                </div>
            </div>

            <ul class="list-unstyled small text-muted mb-3">
                <li>当前账号：{{ $currentUser ?? (auth()->user()->name ?? '未登录') }}</li>
                <li>当前岗位：{{ $positionName ?? '未绑定岗位' }}</li>
                @isset($requiredPermission)
                    <li>所需权限：<code>{{ $requiredPermission }}</code></li>
                @endisset
            </ul>

            <p class="mb-4">
                每个岗位只能进入自己的操作页面。如果这项工作确实属于您的职责，
                请让管理员在「岗位管理」中为您调整岗位或在「权限管理」中授权，刷新后即可访问。
            </p>

            <div class="d-flex gap-2">
                <a href="{{ url('/') }}" class="btn btn-primary">
                    <i class="fa-solid fa-gauge-high me-1"></i>返回我的工作台
                </a>
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">返回上一页</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
