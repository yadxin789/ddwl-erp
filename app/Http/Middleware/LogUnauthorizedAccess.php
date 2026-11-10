<?php

namespace App\Http\Middleware;

use App\Models\Security\SecurityEvent;
use App\Support\NavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * 越权访问审计（§8.2）
 *
 * 只做"记录 + 渲染 403 页面"，不改变授权判断（授权仍由 permission 中间件负责）。
 * 记录失败不得影响正常响应：任何异常都吞掉，只写日志文件。
 */
class LogUnauthorizedAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 403) {
            return $response;
        }

        try {
            $user = $request->user();
            $position = app(NavigationService::class)->forUser($user);

            SecurityEvent::create([
                'event_type' => 'unauthorized_access',
                'user_id' => $user?->getAuthIdentifier(),
                'ip_address' => $request->ip(),
                'level' => 'warning',
                'detail' => json_encode([
                    'route' => optional($request->route())->getName(),
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'position' => $position['position_name'],
                    'position_keys' => $position['position_keys'],
                    'user_agent' => substr((string) $request->userAgent(), 0, 200),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            logger()->warning('unauthorized_access audit failed: ' . $e->getMessage());
        }

        return $response;
    }
}
