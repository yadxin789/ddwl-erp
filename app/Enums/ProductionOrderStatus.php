<?php

namespace App\Enums;

/**
 * 生产工单状态枚举
 * 对应 production_orders.status 字段（P1 阶段使用）
 */
enum ProductionOrderStatus: string
{
    case Planned = 'planned';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => '已计划',
            self::Released => '已下达',
            self::InProgress => '生产中',
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
