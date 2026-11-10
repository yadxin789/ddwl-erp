<?php
namespace App\Enums;
enum TenantStatus: string {
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    public function label(): string {
        return match($this) { self::Active => '正常', self::Suspended => '已暂停', self::Expired => '已过期' };
    }
}
