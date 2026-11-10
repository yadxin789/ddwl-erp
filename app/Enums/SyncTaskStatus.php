<?php
namespace App\Enums;
enum SyncTaskStatus: string {
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';
    public function label(): string {
        return match($this) { self::Active => '运行中', self::Paused => '已暂停', self::Error => '异常' };
    }
}
