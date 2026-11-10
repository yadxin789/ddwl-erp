<?php

namespace App\Enums;

enum SalesQuoteStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Converted = 'converted';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Confirmed => '已确认',
            self::Converted => '已转订单',
            self::Expired => '已过期',
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
