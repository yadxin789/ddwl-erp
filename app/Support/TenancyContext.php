<?php

namespace App\Support;

/**
 * P3-4 当前请求的租户上下文。
 * 空值 = 平台账号（看全量）；非空 = 只允许访问该租户的数据。
 */
class TenancyContext
{
    private ?int $tenantId = null;

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }
}
