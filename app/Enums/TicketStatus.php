<?php
namespace App\Enums;
enum TicketStatus: string {
    case Open = 'open';
    case Processing = 'processing';
    case Closed = 'closed';
    public function label(): string {
        return match($this) { self::Open => '待处理', self::Processing => '处理中', self::Closed => '已关闭' };
    }
}
