<?php
namespace App\Enums;
enum InspectionResult: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    public function label(): string {
        return match($this) { self::Pending => '待检', self::Accepted => '合格', self::Rejected => '不合格' };
    }
}
