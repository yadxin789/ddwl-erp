<?php

namespace App\Enums;

/**
 * BOM 状态枚举
 * 对应 bom_headers.status 字段
 */
enum BomStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Obsolete = 'obsolete';

    public function label(): string
    {
        return match ($this) {
            self::Draft => '草稿',
            self::Approved => '已审核',
            self::Obsolete => '已作废',
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
