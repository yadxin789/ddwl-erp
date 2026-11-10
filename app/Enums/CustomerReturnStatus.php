<?php

namespace App\Enums;

enum CustomerReturnStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case Inspecting = 'inspecting';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待接收',
            self::Received => '已接收',
            self::Inspecting => '质检中',
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
