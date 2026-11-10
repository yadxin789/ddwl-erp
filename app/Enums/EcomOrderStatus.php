<?php
namespace App\Enums;
enum EcomOrderStatus: string {
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Refund = 'refund';
    case Closed = 'closed';
    public function label(): string {
        return match($this) { self::Pending => '待付款', self::Paid => '已付款', self::Shipped => '已发货', self::Completed => '已完成', self::Refund => '退款中', self::Closed => '已关闭' };
    }
}
