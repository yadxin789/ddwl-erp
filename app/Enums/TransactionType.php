<?php

namespace App\Enums;

/**
 * 库存流水事务类型枚举
 * 对应 inventory_transactions.txn_type 字段
 */
enum TransactionType: string
{
    case Receipt = 'receipt';          // 入库
    case Issue = 'issue';              // 出库
    case Transfer = 'transfer';        // 调拨
    case StatusChange = 'status_change'; // 状态转换
    case Adjust = 'adjust';            // 盘点调整
    case ReturnIn = 'return_in';       // 退货入库
    case ScrapOut = 'scrap_out';       // 报废出库
    case Reserve = 'reserve';          // 预留
    case ReleaseReservation = 'release_reservation'; // 释放预留

    public function label(): string
    {
        return match ($this) {
            self::Receipt => '入库',
            self::Issue => '出库',
            self::Transfer => '调拨',
            self::StatusChange => '状态转换',
            self::Adjust => '盘点调整',
            self::ReturnIn => '退货入库',
            self::ScrapOut => '报废出库',
            self::Reserve => '预留',
            self::ReleaseReservation => '释放预留',
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
