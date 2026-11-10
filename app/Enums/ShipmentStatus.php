<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Picked = 'picked';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Picked => '已拣货',
            self::Shipped => '已发货',
            self::Delivered => '已送达',
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
