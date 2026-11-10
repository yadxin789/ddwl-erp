<?php

namespace App\Services;

use App\Models\Marketing\AftersalesTicket;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\Promotion;
use App\Support\NumberGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * 营销服务：促销 / 优惠券 / 售后工单
 * P1-03：业务逻辑（单号、默认状态）下沉到 Service
 */
class MarketingService
{
    public function createPromotion(array $data): Promotion
    {
        Log::info('营销促销创建', ['user_id' => Auth::id(), 'code' => $data['code']]);
        $data['status'] = true;

        return Promotion::create($data);
    }

    public function createCoupon(array $data): Coupon
    {
        Log::info('营销优惠券创建', ['user_id' => Auth::id(), 'name' => $data['name']]);
        $data['coupon_no'] = NumberGenerator::generate('coupon');
        $data['status'] = true;

        return Coupon::create($data);
    }

    public function createTicket(array $data): AftersalesTicket
    {
        Log::info('售后工单创建', ['user_id' => Auth::id(), 'customer' => $data['customer_name']]);
        $data['ticket_no'] = NumberGenerator::generate('ticket');
        $data['status'] = 'open';

        return AftersalesTicket::create($data);
    }
}
