<?php
namespace App\Enums;
enum PaymentDirection: string {
    case In = 'in';
    case Out = 'out';
    public function label(): string {
        return match($this) { self::In => '收款', self::Out => '付款' };
    }
}
