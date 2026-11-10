<?php
namespace App\Enums;
enum InvoiceStatus: string {
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';
    public function label(): string {
        return match($this) { self::Unpaid => '未付款', self::Partial => '部分付款', self::Paid => '已付清', self::Void => '已作废' };
    }
}
