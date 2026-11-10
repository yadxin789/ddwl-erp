<?php
namespace App\Enums;
enum CrmLeadStatus: string {
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Lost = 'lost';
    case Converted = 'converted';
    public function label(): string {
        return match($this) { self::New => '新线索', self::Contacted => '已联系', self::Qualified => '已转化', self::Lost => '已丢失', self::Converted => '已成单' };
    }
}
