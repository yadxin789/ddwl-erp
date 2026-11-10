<?php
namespace App\Enums;
enum OutboxStatus: string {
    case Pending = 'pending';
    case Published = 'published';
    case Failed = 'failed';
    public function label(): string {
        return match($this) { self::Pending => '待发布', self::Published => '已发布', self::Failed => '发布失败' };
    }
}
