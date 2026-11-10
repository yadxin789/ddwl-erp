<?php
namespace App\Enums;
enum VoiceRoomStatus: string {
    case Waiting = 'waiting';
    case Ongoing = 'ongoing';
    case Ended = 'ended';
    public function label(): string {
        return match($this) { self::Waiting => '等待开始', self::Ongoing => '进行中', self::Ended => '已结束' };
    }
}
