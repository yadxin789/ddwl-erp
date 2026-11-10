<?php
namespace App\Enums;
enum ProcurementPlanStatus: string {
    case Draft = 'draft';
    case Approved = 'approved';
    case Ordered = 'ordered';
    case Received = 'received';
    public function label(): string {
        return match($this) { self::Draft => '草稿', self::Approved => '已审批', self::Ordered => '已下单', self::Received => '已到货' };
    }
}
