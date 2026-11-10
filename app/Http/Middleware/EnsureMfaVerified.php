<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * P3-3：已开启 MFA 的用户在本次登录必须完成一次 TOTP/恢复码验证。
 * 放行 mfa.* 与 logout，避免把自己锁在验证页无法退出。
 */
class EnsureMfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->mfa_enabled_at) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if ($route === 'logout' || ($route && str_starts_with($route, 'mfa.'))) {
            return $next($request);
        }

        $verifiedAt = $request->session()->get('mfa_verified_at');
        $userId = $request->session()->get('mfa_user_id');
        if ($verifiedAt && $userId && (int) $userId === (int) $user->id) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(423, '需要完成二次验证。');
        }

        return redirect()->route('mfa.verify');
    }
}
