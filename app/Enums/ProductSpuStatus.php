<?php
namespace App\Enums;
enum ProductSpuStatus: string {
    case Draft = 'draft';
    case OnSale = 'on_sale';
    case OffSale = 'off_sale';
    public function label(): string {
        return match($this) { self::Draft => '草稿', self::OnSale => '在售', self::OffSale => '下架' };
    }
}
