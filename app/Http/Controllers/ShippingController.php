<?php

namespace App\Http\Controllers;

use App\Enums\SalesOrderStatus;
use App\Models\Sales\SalesOrder;
use Illuminate\Http\Request;

/**
 * 发货作业台（G1）
 *
 * 为什么要有这一页：v1.3 之前仓管没有自己的发货入口，只能借销售订单详情页发货，
 * 因此被迫持有 sales.view（跨岗位后门）。本页只做"仓管视角的发货"，权限 sales.ship。
 */
class ShippingController extends Controller
{
    public function index(Request $request)
    {
        $orders = SalesOrder::query()
            ->with(['customer', 'lines.material'])
            ->whereIn('status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Picking->value])
            ->when($request->q, fn ($q, $kw) => $q->where('order_no', 'like', "%{$kw}%"))
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->paginate(10)->withQueryString();

        return view('shipping.index', compact('orders'));
    }
}
