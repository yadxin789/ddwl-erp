<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\PaymentStoreRequest;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierBill;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Supplier;
use App\Support\NumberGenerator;
use App\Services\ApprovalService;
use App\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FinanceController extends Controller
{
    public function __construct(
        protected ApprovalService $approvals,
        protected SettlementService $settlements
    ) {
    }
    public function invoices(Request $request)
    {
        $invoices = CustomerInvoice::with('customer')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);
        $customers = Customer::orderBy('customer_name')->get();
        // P1-03：发票可按销售订单生成（金额默认取已发货行金额）
        $orders = \App\Models\Sales\SalesOrder::withoutGlobalScopes()
            ->whereIn('status', ['confirmed', 'picking', 'shipped', 'completed'])
            ->orderByDesc('id')->limit(100)->get();

        return view('finance.invoices', compact('invoices', 'customers', 'orders'));
    }

    public function invoiceStore(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'invoice_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        // 按销售订单/发货单开票：金额默认 = 已发货数量 × 单价（无发货时退化为订单金额）
        if (! empty($data['sales_order_id'])) {
            $order = \App\Models\Sales\SalesOrder::withoutGlobalScopes()->with('lines')->findOrFail($data['sales_order_id']);
            if ((int) $order->customer_id !== (int) $data['customer_id']) {
                return back()->withErrors(['customer_id' => '所选销售订单不属于该客户'])->withInput();
            }
            if (empty($data['amount'])) {
                $data['amount'] = round((float) $order->lines->sum(fn ($l) => (float) ($l->shipped_qty ?: $l->qty) * (float) $l->unit_price), 2);
            }
        }

        $data['amount'] = $data['amount'] ?? 0;
        $data['invoice_no'] = NumberGenerator::generate('invoice');
        // P0-08：发票按开票责任人归属（owner_id 空会使该单在受限岗位的列表里不可见）
        $data['owner_id'] = auth()->id();
        // P1-01：开票要过"会计 → 总经理"审批，审批期间状态 pending_approval（不计 AR）
        $data['status'] = app(\App\Models\Approval\ApprovalFlow::class)->where('business_type', 'invoice.issue')->where('status', true)->exists()
            ? 'pending_approval'
            : 'unpaid';
        $invoice = CustomerInvoice::create($data);

        try {
            $this->approvals->start('invoice.issue', $invoice->id);
            if ($data['status'] === 'pending_approval') {
                return back()->with('success', "发票 {$invoice->invoice_no} 已提交审批，审批通过后计入应收。");
            }
        } catch (\DomainException|\InvalidArgumentException $e) {
            $invoice->delete();

            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', '发票已开具。');
    }

    public function bills(Request $request)
    {
        $bills = SupplierBill::with('supplier')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')->paginate(15);
        $suppliers = Supplier::orderBy('supplier_name')->get();

        return view('finance.bills', compact('bills', 'suppliers'));
    }

    public function billStore(Request $request)
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'bill_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);
        $data['bill_no'] = NumberGenerator::generate('bill');
        $data['status'] = 'unpaid';
        // P0-08：账单按采购责任人归属；录入人是会计时记录 owner_id，避免列表不可见
        $data['owner_id'] = auth()->id();
        SupplierBill::create($data);

        return back()->with('success', '供应商账单已录入。');
    }

    public function payments(Request $request)
    {
        $payments = Payment::with('allocations')->orderByDesc('id')->paginate(20);
        $customers = Customer::orderBy('customer_name')->get();
        $suppliers = Supplier::orderBy('supplier_name')->get();
        $invoices = CustomerInvoice::withoutGlobalScopes()->whereIn('status', ['unpaid', 'partial'])->orderByDesc('id')->limit(200)->get();
        $bills = SupplierBill::withoutGlobalScopes()->whereIn('status', ['unpaid', 'partial'])->orderByDesc('id')->limit(200)->get();

        return view('finance.payments', compact('payments', 'customers', 'suppliers', 'invoices', 'bills'));
    }

    public function paymentStore(PaymentStoreRequest $request)
    {
        $data = $request->validated();
        $data['payment_no'] = NumberGenerator::generate('payment');
        $data['user_id'] = auth()->id();
        // required_if 已保证 in 必有 customer_id、out 必有 supplier_id
        $data['customer_id'] = $data['direction'] === 'in' ? $data['customer_id'] : null;
        $data['supplier_id'] = $data['direction'] === 'out' ? $data['supplier_id'] : null;

        $needsApproval = \App\Models\Approval\ApprovalFlow::where('business_type', 'payment.execute')
            ->where('status', true)->exists();
        $data['status'] = $needsApproval ? 'pending' : 'executed';

        $payment = Payment::create($data);

        // P1-03：登记时即可核销（收款→客户发票，付款→供应商账单）
        if (! empty($request->input('invoice_id')) || ! empty($request->input('bill_id'))) {
            $amount = $request->input('allocate_amount');
            $amount = $amount !== null && $amount !== '' ? (float) $amount : (float) $payment->amount;

            try {
                $this->settlements->allocate($payment->id, [[
                    'invoice_id' => $request->input('invoice_id') ?: null,
                    'bill_id' => $request->input('bill_id') ?: null,
                    'amount' => $amount,
                ]]);
            } catch (\DomainException|\InvalidArgumentException $e) {
                $payment->delete();

                return back()->withErrors(['allocate_amount' => $e->getMessage()])->withInput();
            }
        }

        try {
            $instance = $this->approvals->start('payment.execute', $payment->id);
        } catch (\DomainException|\InvalidArgumentException $e) {
            $payment->delete(); // 审批发起失败不留孤儿流水

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        // 无审批流时收付款登记即执行，核销立即回写
        if (! $instance) {
            $this->settlements->apply($payment->id);
        }

        Log::info('收付款已登记', [
            'payment_id' => $payment->id,
            'user_id' => auth()->id(),
            'direction' => $data['direction'],
            'amount' => $data['amount'],
            'customer_id' => $data['customer_id'],
            'supplier_id' => $data['supplier_id'],
            'approval_instance' => $instance?->id,
        ]);

        if ($instance) {
            return back()->with('success', "收付款 {$payment->payment_no} 已登记，等待审批（会计审核 → 总经理），审批通过后方可执行。");
        }

        return back()->with('success', '收付款已登记。');
    }

    /**
     * P1-01：出纳执行付款——必须在审批通过之后（`payments.status=approved`）。
     */
    public function paymentExecute(\App\Models\Finance\Payment $payment)
    {
        if ($payment->status === 'executed') {
            return back()->withErrors(['payment' => '该笔收付款已执行']);
        }
        if ($payment->status !== 'approved') {
            return back()->withErrors(['payment' => '付款尚未通过审批（会计 → 总经理），不能执行']);
        }

        $payment->update(['status' => 'executed']);
        // P1-03：执行后才把核销金额回写到发票/账单（审批中的不算已收/已付）
        $this->settlements->apply($payment->id);
        Log::info('收付款已执行', ['payment_id' => $payment->id, 'user_id' => auth()->id()]);

        return back()->with('success', "收付款 {$payment->payment_no} 已执行。");
    }

    /* ---------- 出纳 / 会计：现金日记账、银行对账、应收应付、票据（G10） ---------- */

    public function cashbook(Request $request)
    {
        $transactions = \App\Models\Finance\FinancialTransaction::query()
            ->when($request->from, fn ($q, $d) => $q->whereDate('tx_date', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('tx_date', '<=', $d))
            ->orderBy('tx_date')->orderBy('id')
            ->paginate(30)->withQueryString();

        $income = (float) \App\Models\Finance\FinancialTransaction::where('direction', 'in')->sum('amount');
        $expense = (float) \App\Models\Finance\FinancialTransaction::where('direction', 'out')->sum('amount');

        return view('finance.cashbook', compact('transactions', 'income', 'expense'));
    }

    public function cashbookStore(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:32'],
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'summary' => ['nullable', 'string', 'max:200'],
            'tx_date' => ['required', 'date'],
        ]);

        $data['tx_no'] = 'CSH' . now()->format('YmdHis') . str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT);
        \App\Models\Finance\FinancialTransaction::create($data);

        return back()->with('success', '流水已记账。');
    }

    public function bankReconcile()
    {
        $records = \App\Models\Finance\FinanceReconcile::query()
            ->orderByDesc('period')->orderByDesc('id')->paginate(20);

        return view('finance.bank-reconcile', compact('records'));
    }

    public function reconcileStore(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'date'],
            'book_amount' => ['required', 'numeric'],
            'actual_amount' => ['required', 'numeric'],
            'type' => ['nullable', 'string', 'max:16'],
        ]);

        $diff = (float) $data['actual_amount'] - (float) $data['book_amount'];

        \App\Models\Finance\FinanceReconcile::create([
            'reconcile_no' => 'REC' . now()->format('Ymd') . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'period' => $data['period'],
            'type' => $data['type'] ?? 'bank',
            'book_amount' => $data['book_amount'],
            'actual_amount' => $data['actual_amount'],
            'diff_amount' => $diff,
            'status' => abs($diff) < 0.01 ? 'balanced' : 'diff',
        ]);

        return back()->with('success', '对账记录已保存，差异 ' . number_format($diff, 2));
    }

    public function arap()
    {
        // P1-03：审批中（pending_approval）的发票不计入应收，作废的不计入
        $ar = CustomerInvoice::with('customer')->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get();
        $ap = SupplierBill::with('supplier')->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date')->get();

        $today = now()->startOfDay();
        $bucket = function ($rows) use ($today) {
            $result = ['overdue' => 0.0, 'due30' => 0.0, 'later' => 0.0];
            foreach ($rows as $row) {
                $left = (float) $row->amount - (float) $row->paid_amount;
                $due = $row->due_date ? $row->due_date->startOfDay() : null;
                if ($due && $due->lt($today)) {
                    $result['overdue'] += $left;
                } elseif ($due && $due->diffInDays($today) <= 30) {
                    $result['due30'] += $left;
                } else {
                    $result['later'] += $left;
                }
            }

            return $result;
        };

        $arBucket = $bucket($ar);
        $apBucket = $bucket($ap);

        return view('finance.arap', compact('ar', 'ap', 'arBucket', 'apBucket'));
    }

    /**
     * P1-03：供应商三单匹配（PO 订单 → GR 收货 → Bill 账单）差异表
     */
    public function threeWayMatch(Request $request)
    {
        $supplierId = $request->input('supplier_id');

        $pos = \App\Models\Purchasing\PurchaseOrder::withoutGlobalScopes()
            ->with(['supplier', 'lines'])
            ->when($supplierId, fn ($q, $s) => $q->where('supplier_id', $s))
            ->orderByDesc('id')->limit(60)->get();

        $poIds = $pos->pluck('id');

        // 已收货数量（按采购单汇总收货单行数量）
        $received = \Illuminate\Support\Facades\DB::table('goods_receipt_lines as gl')
            ->join('goods_receipts as g', 'g.id', '=', 'gl.receipt_id')
            ->whereIn('g.po_id', $poIds)
            ->selectRaw('g.po_id, gl.material_id, SUM(gl.qty) as qty')
            ->groupBy('g.po_id', 'gl.material_id')
            ->get()
            ->groupBy('po_id');

        $bills = SupplierBill::withoutGlobalScopes()
            ->whereIn('purchase_order_id', $poIds)
            ->get()->groupBy('purchase_order_id');

        $rows = $pos->map(function ($po) use ($received, $bills) {
            $poAmount = round((float) $po->lines->sum(fn ($l) => (float) $l->qty * (float) $l->unit_price), 2);

            // 收货金额 = 实收数量 × 采购单价（按物料匹配订单行）
            $grAmount = 0.0;
            foreach ($received[$po->id] ?? [] as $r) {
                $line = $po->lines->firstWhere('material_id', $r->material_id);
                $grAmount += (float) $r->qty * (float) ($line->unit_price ?? 0);
            }
            $grAmount = round($grAmount, 2);

            $billAmount = round((float) ($bills[$po->id] ?? collect())->sum('amount'), 2);

            return (object) [
                'po' => $po,
                'po_amount' => $poAmount,
                'gr_amount' => $grAmount,
                'bill_amount' => $billAmount,
                'diff' => round($billAmount - $grAmount, 2),
            ];
        });

        $suppliers = Supplier::orderBy('supplier_name')->get();

        return view('finance.three-way-match', compact('rows', 'suppliers', 'supplierId'));
    }

    public function notes()
    {
        $notes = \App\Models\Finance\FinanceNote::query()->orderBy('due_date')->paginate(20);

        return view('finance.notes', compact('notes'));
    }

    public function noteStore(Request $request)
    {
        $data = $request->validate([
            'note_no' => ['required', 'string', 'max:32', 'unique:finance_notes,note_no'],
            'type' => ['required', 'in:bank,commercial'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'drawer' => ['nullable', 'string', 'max:64'],
            'due_date' => ['required', 'date'],
        ]);

        \App\Models\Finance\FinanceNote::create($data + ['status' => 'holding']);

        return back()->with('success', '票据已登记。');
    }
}
