<?php

namespace App\Enums;

enum BomMatchStatus: string
{
    case Matched = 'matched';
    case Multiple = 'multiple';
    case Unmatched = 'unmatched';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Matched => '已匹配',
            self::Multiple => '多匹配',
            self::Unmatched => '未匹配',
            self::Manual => '手动指定',
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
