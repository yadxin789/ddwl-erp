<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\Purchase\PurchaseInspectRequest;
use App\Http\Requests\Purchase\PurchaseOrderStoreRequest;
use App\Http\Requests\Purchase\PurchaseReceiveRequest;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\Material;
use App\Models\MasterData\Supplier;
use App\Models\Purchase\PurchaseRequisition;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Purchasing\PurchaseOrder;
use App\Support\NumberGenerator;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        protected PurchaseOrderService $service,
        protected \App\Services\ApprovalService $approvals
    ) {
    }

    /* ---------- 采购订单 ---------- */

    public function orders(Request $request)
    {
        $orders = PurchaseOrder::with('supplier')
            ->when($request->q, fn ($q, $kw) => $q->where('po_no', 'like', "%{$kw}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('purchase.orders', compact('orders'));
    }

    public function orderCreate()
    {
        $suppliers = Supplier::where('status', 1)->orderBy('supplier_code')->get();
        $materials = Material::where('status', 1)->orderBy('material_code')->get();

        return view('purchase.order-create', compact('suppliers', 'materials'));
    }

    public function orderStore(PurchaseOrderStoreRequest $request)
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
                'remark' => $request->input('line_remark')[$i] ?? null,
            ];
        }

        $po = $this->service->createOrder($request->only(['supplier_id', 'order_date', 'expected_date', 'remark']), $lines);

        return redirect()->route('purchase.orders.show', $po)->with('success', "采购订单 {$po->po_no} 已创建。");
    }

    public function orderShow(PurchaseOrder $order)
    {
        $order->load(['supplier', 'buyer', 'lines.material', 'receipts.lines.material']);

        return view('purchase.order-show', compact('order'));
    }

    public function orderConfirm(PurchaseOrder $order)
    {
        try {
            // P1-01：配置了审批流就"先挂审批、后执行"——审批期间订单保持 draft
            $instance = $this->approvals->start('purchase_order.confirm', $order->id, null, ['action' => 'confirm']);
            if ($instance) {
                return redirect()->route('purchase.orders.show', $order)
                    ->with('success', "采购订单 {$order->po_no} 已提交审批（单号：#{$instance->id}），审批通过后自动确认。");
            }

            $po = $this->service->confirm($order->id);

            return redirect()->route('purchase.orders.show', $po)->with('success', "采购订单 {$po->po_no} 已确认。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['confirm' => $e->getMessage()]);
        }
    }

    public function orderClose(PurchaseOrder $order)
    {
        try {
            // P1-01：关闭也复用 purchase_order.confirm 流，payload 区分动作
            $instance = $this->approvals->start('purchase_order.confirm', $order->id, null, ['action' => 'close']);
            if ($instance) {
                return redirect()->route('purchase.orders.show', $order)
                    ->with('success', "采购订单 {$order->po_no} 关闭申请已提交审批（单号：#{$instance->id}）。");
            }

            $po = $this->service->closeOrder($order->id);

            return redirect()->route('purchase.orders.show', $po)->with('success', "采购订单 {$po->po_no} 已关闭。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['close' => $e->getMessage()]);
        }
    }

    /* ---------- 收货 ---------- */

    public function receiveCreate(PurchaseOrder $order)
    {
        $order->load('lines.material');
        $warehouses = Warehouse::where('status', 1)->orderBy('warehouse_code')->get();

        return view('purchase.receive-create', compact('order', 'warehouses'));
    }

    public function receiveStore(PurchaseReceiveRequest $request)
    {
        $lines = [];
        foreach ($request->input('material_id', []) as $i => $mid) {
            if (! $mid) {
                continue;
            }
            $lines[] = [
                'material_id' => (int) $mid,
                'lot_no' => $request->input('lot_no')[$i],
                'qty' => (float) $request->input('qty')[$i],
                'supplier_batch' => $request->input('supplier_batch')[$i] ?? null,
            ];
        }

        try {
            $receipt = $this->service->receive($request->only(['po_id', 'receipt_date', 'warehouse_id', 'location_id']), $lines);

            return redirect()->route('purchase.receipts.show', $receipt)
                ->with('success', "收货单 {$receipt->receipt_no} 已创建，库存已入 qc_hold（待质检）。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['receive' => $e->getMessage()]);
        }
    }

    /* ---------- 收货单 ---------- */

    /**
     * P0-06：收货上架作业台（无参入口）。质检合格 = 库存已转 available，等待上架/归档。
     */
    public function putawayQueue()
    {
        $receipts = GoodsReceipt::with(['supplier', 'purchaseOrder', 'warehouse'])
            ->where('status', 'qc_passed')
            ->orderByDesc('id')->paginate(20);

        return view('purchase.receipt-queue', ['receipts' => $receipts, 'queue' => 'putaway']);
    }

    /**
     * P0-06：送检作业台（无参入口）。待质检单据 + 判定入口（判定权归 IQC）。
     */
    public function inspectQueue()
    {
        $receipts = GoodsReceipt::with(['supplier', 'purchaseOrder', 'warehouse'])
            ->where('status', 'pending_qc')
            ->orderByDesc('id')->paginate(20);

        return view('purchase.receipt-queue', ['receipts' => $receipts, 'queue' => 'inspect']);
    }

    public function receipts(Request $request)
    {
        $receipts = GoodsReceipt::with(['supplier', 'purchaseOrder'])
            ->when($request->q, fn ($q, $kw) => $q->where('receipt_no', 'like', "%{$kw}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);

        return view('purchase.receipts', compact('receipts'));
    }

    public function receiptShow(int $receipt)
    {
        // P1-06：收货单归属仓库部门，IQC/品质视角按部门范围取不到 → 绕过行级范围，授权在权限层（purchase.view）
        $model = GoodsReceipt::withoutGlobalScopes()->findOrFail($receipt);
        $model->load(['purchaseOrder', 'supplier', 'warehouse', 'lines.material']);

        return view('purchase.receipt-show', ['receipt' => $model]);
    }

    public function inspect(PurchaseInspectRequest $request, int $receipt)
    {
        $model = GoodsReceipt::withoutGlobalScopes()->findOrFail($receipt);
        try {
            $result = $this->service->inspect($model->id, $request->validated()['lines']);

            return redirect()->route('purchase.receipts.show', $result)
                ->with('success', "质检完成：{$result->status}。合格品已转 available，不合格品已转 blocked。");
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['inspect' => $e->getMessage()]);
        }
    }

    /* ---------- 采购申请 ---------- */

    public function requisitions(Request $request)
    {
        $rows = PurchaseRequisition::with('requester')
            ->when($request->q, fn ($q, $kw) => $q->where('req_no', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        return view('purchase.requisitions', compact('rows'));
    }

    public function requisitionCreate()
    {
        $materials = Material::where('status', 1)->orderBy('material_code')->get();

        return view('purchase.requisition-create', compact('materials'));
    }

    public function requisitionStore(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:128'],
            'need_date' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.material_id' => ['required', 'exists:materials,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $req = PurchaseRequisition::create([
            'req_no' => NumberGenerator::generate('purchase_requisition'),
            'title' => $data['title'],
            'requester_id' => auth()->id(),
            'need_date' => $data['need_date'] ?? null,
            'status' => 'draft',
            'remark' => $data['remark'] ?? null,
        ]);
        foreach ($data['lines'] as $line) {
            $req->lines()->create($line);
        }

        return redirect()->route('purchase.requisitions')->with('success', "采购申请 {$req->req_no} 已创建。");
    }
}
