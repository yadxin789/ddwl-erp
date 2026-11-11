<?php

namespace App\Ai\Contracts;

use App\Models\Organization\User;

/**
 * 可由大模型通过 Function Calling 调用的只读工具。
 *
 * 每个工具必须自己完成权限与数据范围校验；注册表不会替工具兜底。
 */
interface AiTool
{
    /** 工具名，必须与技能 config.tools 中的名称一致。 */
    public function name(): string;

    /** 给模型看的中文说明，需明确能查什么、不能查什么。 */
    public function description(): string;

    /** OpenAI function.parameters 的 JSON Schema。 */
    public function schema(): array;

    /** 执行并返回纯文本结果。抛异常表示失败，由调用层转为工具错误消息。 */
    public function execute(array $arguments, User $user): string;
}
