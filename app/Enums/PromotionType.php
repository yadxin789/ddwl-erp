<?php
namespace App\Enums;
enum PromotionType: string {
    case Discount = 'discount';
    case FullGift = 'full_gift';
    case Seckill = 'seckill';
    public function label(): string {
        return match($this) { self::Discount => '折扣', self::FullGift => '满赠', self::Seckill => '秒杀' };
    }
}
