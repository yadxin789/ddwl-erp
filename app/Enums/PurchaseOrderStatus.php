<?php

namespace App\Enums;

/**
 * 采购订单状态枚举
 * 对应 purchase_orders.status 字段（P1 阶段使用）
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Confirmed => '已确认',
            self::Received => '已收货',
            self::Closed => '已关闭',
            self::Cancelled => '已取消',
        };
    }

    public static function options(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }

        return $result;
    }
}
