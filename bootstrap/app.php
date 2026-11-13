<?php

use App\Http\Middleware\LogUnauthorizedAccess;
use App\Http\Middleware\SensitiveAccessLogMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // spatie/laravel-permission 中间件别名
        $middleware->web(append: [LogUnauthorizedAccess::class]);

        // OnlyOffice DocumentServer 保存回调为服务器请求，不带 CSRF 会话
        $middleware->validateCsrfTokens(except: [
            'rnd/collab/callback/*',
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'sensitive' => SensitiveAccessLogMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // §8.2：403 统一渲染（自定义页），并暴露当前岗位，便于用户自查
        // 同时覆盖 spatie 抛出的 UnauthorizedException（它继承 HttpException，不继承 AccessDeniedHttpException）
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException | AccessDeniedHttpException $e, Request $request) {
            $position = app(\App\Support\NavigationService::class)->forUser($request->user());
            $requiredPermission = $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json(['message' => '该页面不属于您的岗位'], 403);
            }

            return response()->view('errors.403', [
                'currentUser' => $request->user()?->identity_label,
                'positionName' => $position['position_name'] ?? '未绑定岗位',
                'requiredPermission' => $requiredPermission,
            ], 403);
        });

        // P1-05：生产环境不向用户暴露 SQL/堆栈，业务异常回退上一页带错误
        $exceptions->render(function (\DomainException | \InvalidArgumentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            if ($request->is('*') && session()->isStarted()) {
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        });

        $exceptions->render(function (ModelNotFoundException | NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => '记录不存在'], 404);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
