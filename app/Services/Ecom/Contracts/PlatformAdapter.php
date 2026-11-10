<?php

namespace App\Services\Ecom\Contracts;

/**
 * P1-07 平台适配器契约（本期只做接入与配置校验，交易动作一律"待接入"）
 */
interface PlatformAdapter
{
    /** 平台标识（预置 key 或 custom:xxx） */
    public function key(): string;

    /** 能力声明：pullOrders/pushShipment/ackOrder 本期全部 false */
    public function capabilities(): array;

    /**
     * 连接自检：只校验配置是否可用，不抛异常。
     * @return array{ok:bool,message:string,code:string}
     */
    public function verifyCredentials(array $credentials, array $options = []): array;

    /** 平台订单字段 → 本地字段映射（默认 identity，供后续交易闭环复用） */
    public function mapOrderFields(array $payload): array;

    /** 平台状态 → 本地状态 */
    public function mapStatus(string $platformStatus): string;
}
