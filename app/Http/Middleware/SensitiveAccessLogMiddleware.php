<?php

namespace App\Http\Middleware;

use App\Models\Security\SensitiveAccessLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 敏感操作记录中间件：挂在导出/权限修改/工资查看等路由上，写 sensitive_access_logs
 */
class SensitiveAccessLogMiddleware
{
    public function handle(Request $request, Closure $next, string $action = 'view'): Response
    {
        $response = $next($request);

        if (Auth::check() && $response->getStatusCode() < 400) {
            SensitiveAccessLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'target' => $request->path(),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
