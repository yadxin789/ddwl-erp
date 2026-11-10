<?php

namespace App\Http\Middleware;

use App\Support\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** P3-4：每个请求按登录用户的 tenant_id 设置租户上下文；平台账号清空。 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->user()?->tenant_id;
        app(TenancyContext::class)->set($tenantId ? (int) $tenantId : null);

        return $next($request);
    }
}
