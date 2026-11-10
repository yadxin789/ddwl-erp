<?php
namespace App\Enums;
enum HrLeaveType: string {
    case Annual = 'annual';
    case Sick = 'sick';
    case Personal = 'personal';
    case Marriage = 'marriage';
    public function label(): string {
        return match($this) { self::Annual => '年假', self::Sick => '病假', self::Personal => '事假', self::Marriage => '婚假' };
    }
}
