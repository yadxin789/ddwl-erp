<?php

namespace App\Enums;

enum ReturnDisposition: string
{
    case BackToStock = 'back_to_stock';
    case Repair = 'repair';
    case Scrap = 'scrap';
    case ReturnToSupplier = 'return_to_supplier';

    public function label(): string
    {
        return match ($this) {
            self::BackToStock => '回库',
            self::Repair => '维修',
            self::Scrap => '报废',
            self::ReturnToSupplier => '退回供应商',
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
