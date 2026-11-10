<?php

namespace App\Enums;

/**
 * 销售订单状态枚举
 * 对应 sales_orders.status 字段（P1 阶段使用）
 */
enum SalesOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Picking = 'picking';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Confirmed => '已确认',
            self::Picking => '拣货中',
            self::Shipped => '已发货',
            self::Completed => '已完成',
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
