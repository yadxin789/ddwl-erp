<?php
namespace App\Enums;
enum RecruitmentStatus: string {
    case Open = 'open';
    case Closed = 'closed';
    public function label(): string {
        return match($this) { self::Open => '招聘中', self::Closed => '已关闭' };
    }
}
