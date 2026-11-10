<?php

namespace App\Enums;

/**
 * 库存状态枚举
 * 对应 inventory_stock.status / inventory_status_changes 字段
 */
enum StockStatus: string
{
    case Available = 'available';
    case QcHold = 'qc_hold';
    case Blocked = 'blocked';
    case Reserved = 'reserved';
    case Returned = 'returned';
    case Repair = 'repair';
    case Scrap = 'scrap';

    public function label(): string
    {
        return match ($this) {
            self::Available => '可用',
            self::QcHold => '待检',
            self::Blocked => '冻结',
            self::Reserved => '预留',
            self::Returned => '退货待检',
            self::Repair => '维修中',
            self::Scrap => '报废',
        };
    }

    /**
     * 全部枚举（含值与中文标签）
     */
    public static function options(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }

        return $result;
    }
}
