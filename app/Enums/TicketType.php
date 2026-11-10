<?php
namespace App\Enums;
enum TicketType: string {
    case Return = 'return';
    case Refund = 'refund';
    case Exchange = 'exchange';
    case Complaint = 'complaint';
    public function label(): string {
        return match($this) { self::Return => '退货', self::Refund => '退款', self::Exchange => '换货', self::Complaint => '投诉' };
    }
}
