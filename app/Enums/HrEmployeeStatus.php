<?php
namespace App\Enums;
enum HrEmployeeStatus: string {
    case Active = 'active';
    case OnLeave = 'leave';
    case Quit = 'quit';
    public function label(): string {
        return match($this) { self::Active => '在职', self::OnLeave => '离职休假', self::Quit => '已离职' };
    }
}
