<?php

namespace App\Enums;

enum GoodsReceiptStatus: string
{
    case PendingQc = 'pending_qc';
    case QcPassed = 'qc_passed';
    case QcFailed = 'qc_failed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PendingQc => '待质检',
            self::QcPassed => '质检合格',
            self::QcFailed => '质检不合格',
            self::Closed => '已结案',
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
