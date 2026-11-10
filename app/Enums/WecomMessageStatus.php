<?php
namespace App\Enums;
enum WecomMessageStatus: string {
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    public function label(): string {
        return match($this) { self::Pending => '待发送', self::Sent => '已发送', self::Failed => '发送失败' };
    }
}
