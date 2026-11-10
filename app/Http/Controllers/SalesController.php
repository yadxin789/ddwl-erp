<?php

namespace App\Http\Controllers;

use App\Enums\SalesOrderStatus;
use App\Http\Requests\Sales\SalesOrderStoreRequest;
use App\Http\Requests\Sales\SalesQuoteStoreRequest;
use App\Http\Requests\Sales\SalesReturnStoreRequest;
use App\Http\Requests\Sales\SalesShipRequest;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Material;
use App\Models\Sales\CustomerReturn;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesQuote;
use App\Services\SalesOrderService;
use App\Services\SalesReturnService;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    public function __construct(
        protected SalesOrderService $orders,
        protected SalesReturnService $returns
    ) {
    }

    /* ---------- 报价单 ---------- */

    public function quotes(Request $request)
    {
        $quotes = SalesQuote::with('customer')
            ->when($request->q, fn ($q, $kw) => $q->where('quote_no', 'like', "%{$kw}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('sales.quotes', compact('quotes'));
    }

    public function quoteCreate()
    {
        $customers = Customer::where('status', 1)->orderBy('customer_name')->get();
        $materials = Material::where('status', 1)->orderBy('material_code')->get();

        return view('sales.quote-create', compact('customers', 'materials'));
    }

    public function quoteStore(SalesQuoteStoreRequest $request)
    {
        $validated = $request->validated();

        $lines = $this->buildLines($request);
        $quote = $this->orders->createQuote($request->only(['customer_id', 'quote_date', 'valid_until', 'remark']), $lines);

        return redirect()->route('sales.quotes')->with('success', "报价单 {$quote->quote_no} 已创建。");
    }

    public function quoteConvert(SalesQuote $quote)
    {
        try {
            $order = $this->orders->convertQuote($quote->id);

            return redirect()->route('sales.orders.show', $order)->with('success', "报价已转为销售订单 {$order->order_no}。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['convert' => $e->getMessage()]);
        }
    }

    /* ---------- 销售订单 ---------- */

    public function orders(Request $request)
    {
        $orders = SalesOrder::with('customer')
            ->when($request->q, fn ($q, $kw) => $q->where('order_no', 'like', "%{$kw}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('sales.orders', compact('orders'));
    }

    public function orderCreate()
    {
        $customers = Customer::where('status', 1)->orderBy('customer_name')->get();
        $materials = Material::where('status', 1)->orderBy('material_code')->get();
        $quotes = SalesQuote::where('status', SalesOrderStatus::Draft->value)->orderByDesc('id')->get();

        return view('sales.order-create', compact('customers', 'materials', 'quotes'));
    }

    public function orderStore(SalesOrderStoreRequest $request)
    {
        $validated = $request->validated();

        $lines = $this->buildLines($request);
        $order = $this->orders->createOrder($request->only(['customer_id', 'quote_id', 'order_date', 'delivery_date', 'remark']), $lines);

        return redirect()->route('sales.orders.show', $order)->with('success', "销售订单 {$order->order_no} 已创建。");
    }

    public function orderShow(SalesOrder $order)
    {
        $order->load(['customer', 'lines.material', 'shipments']);
        $result = $this->orders->calculateShortages($order);

        return view('sales.order-show', [
            'order' => $order,
            'shortages' => $result['shortages'],
            'shortageTotal' => $result['total'],
        ]);
    }

    public function orderConfirm(SalesOrder $order)
    {
        try {
            $result = $this->orders->confirm($order->id);
            $msg = "订单 {$order->order_no} 已确认并预留库存。";
            if ($result['shortage_total'] > 0) {
                $msg .= " 库存不足，需生产 {$result['shortage_total']} 件。";
            }

            return redirect()->route('sales.orders.show', $order)->with('success', $msg);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }
    }

    public function orderShip(SalesOrder $order, SalesShipRequest $request)
    {
        $validated = $request->validated();

        $items = [];
        foreach ($request->input('ship_qty', []) as $lineId => $qty) {
            if ((float) $qty > 0) {
                $items[(int) $lineId] = (float) $qty;
            }
        }
        if (empty($items)) {
            return back()->withErrors(['ship' => '请填写至少一行出货数量']);
        }

        try {
            $shipment = $this->orders->ship($order->id, $items, $request->only(['shipment_date', 'logistics_company', 'tracking_no']));

            return redirect()->route('sales.orders.show', $order)->with('success', "出货单 {$shipment->shipment_no} 已创建，库存已扣减。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['ship' => $e->getMessage()]);
        }
    }

    /* ---------- 客户退货 ---------- */

    public function returns(Request $request)
    {
        $returns = CustomerReturn::with('customer')
            ->when($request->q, fn ($q, $kw) => $q->where('return_no', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        return view('sales.returns', compact('returns'));
    }

    public function returnCreate()
    {
        $customers = Customer::where('status', 1)->orderBy('customer_name')->get();
        $materials = Material::where('status', 1)->orderBy('material_code')->get();
        $orders = SalesOrder::whereIn('status', [SalesOrderStatus::Confirmed->value, SalesOrderStatus::Picking->value, SalesOrderStatus::Shipped->value])->orderByDesc('id')->get();

        return view('sales.return-create', compact('customers', 'materials', 'orders'));
    }

    public function returnStore(SalesReturnStoreRequest $request)
    {
        $validated = $request->validated();

        $lines = [];
        foreach ($request->input('material_id', []) as $i => $mid) {
            $lines[] = [
                'material_id' => (int) $mid,
                'lot_no' => $request->input('lot_no')[$i] ?? null,
                'qty' => (float) $request->input('qty')[$i],
            ];
        }

        try {
            $ret = $this->returns->create($request->only(['customer_id', 'sales_order_id', 'return_reason']), $lines);

            // P1-01：退货要经销售主管审批，通过后才把库存 available → returned
            $instance = app(\App\Services\ApprovalService::class)
                ->start('sales_return.approve', $ret->id);
            if ($instance) {
                return redirect()->route('sales.returns')
                    ->with('success', "退货单 {$ret->return_no} 已创建，等待销售主管审批（审批通过后库存转 returned）。");
            }

            $this->returns->apply($ret->id);

            return redirect()->route('sales.returns')->with('success', "退货单 {$ret->return_no} 已创建，库存已转 returned（待质检）。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['return' => $e->getMessage()]);
        }
    }

    private function buildLines(Request $request): array
    {
        $lines = [];
        foreach ($request->input('material_id', []) as $i => $mid) {
            if (! $mid) {
                continue;
            }
            $lines[] = [
                'material_id' => (int) $mid,
                'qty' => (float) $request->input('qty')[$i],
                'unit_price' => (float) $request->input('unit_price')[$i],
                'delivery_days' => $request->input('delivery_days')[$i] ?? null,
                'remark' => $request->input('line_remark')[$i] ?? null,
            ];
        }

        return $lines;
    }
}
