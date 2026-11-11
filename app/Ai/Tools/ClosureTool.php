<?php

namespace App\Ai\Tools;

use App\Ai\Contracts\AiTool;
use App\Models\Organization\User;
use Closure;

/**
 * 轻量工具实现：把名称、说明、参数结构和执行闭包组合起来，
 * 避免为每个只读查询新建一个几乎相同的类文件。
 */
class ClosureTool implements AiTool
{
    public function __construct(
        private readonly string $toolName,
        private readonly string $toolDescription,
        private readonly array $toolSchema,
        private readonly Closure $handler,
    ) {
    }

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function schema(): array
    {
        return $this->toolSchema;
    }

    public function execute(array $arguments, User $user): string
    {
        return (string) ($this->handler)($arguments, $user);
    }
}
