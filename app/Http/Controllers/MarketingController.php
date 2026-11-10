<?php

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Enums\TicketType;
use App\Models\Marketing\AftersalesTicket;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\Promotion;
use App\Services\MarketingService;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function __construct(protected MarketingService $service)
    {
    }

    public function promotions()
    {
        $promos = Promotion::orderByDesc('id')->paginate(15);

        return view('marketing.promotions', compact('promos'));
    }

    public function promotionStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:promotions,code'],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'in:' . implode(',', array_column(PromotionType::cases(), 'value'))],
            'value' => ['required', 'numeric', 'min:0'],
        ]);
        $this->service->createPromotion($data);

        return back()->with('success', '促销活动已创建。');
    }

    public function coupons()
    {
        $coupons = Coupon::orderByDesc('id')->paginate(15);

        return view('marketing.coupons', compact('coupons'));
    }

    public function couponStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'amount' => ['required', 'numeric', 'min:0'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'total_qty' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->service->createCoupon($data);

        return back()->with('success', '优惠券已创建。');
    }

    public function tickets(Request $request)
    {
        $tickets = AftersalesTicket::when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('marketing.tickets', compact('tickets'));
    }

    public function ticketStore(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:64'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'type' => ['required', 'in:' . implode(',', array_column(TicketType::cases(), 'value'))],
            'description' => ['required', 'string', 'max:1000'],
        ]);
        $this->service->createTicket($data);

        return back()->with('success', '售后工单已创建。');
    }
}
