<?php

namespace App\Enums;

/**
 * 用户状态枚举
 * 对应 users.status 字段
 */
enum UserStatus: int
{
    case Active = 1;
    case Inactive = 0;

    public function label(): string
    {
        return match ($this) {
            self::Active => '启用',
            self::Inactive => '禁用',
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
