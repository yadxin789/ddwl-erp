<?php

namespace App\Services;

use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\SupplierBill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 财务核销（P1-03 / D-04）
 *
 * 口径：
 * - 核销明细落 `payment_allocations`（一条收付款可拆给多张发票/账单）
 * - 只有 `payments.status = executed` 的收付款才计入 `paid_amount`（审批中的收款不虚增应收）
 * - `paid_amount` 由核销明细**重算**（不是累加），所以重复执行 apply 天然幂等
 * - 发票/账单状态按规定重算：0 → unpaid，部分 → partial，满额 → paid；审批中/作废状态不覆盖
 */
class SettlementService
{
    /**
     * 登记核销明细（同一条收付款对同一张发票/账单重复提交会被忽略，不重复计入）。
     *
     * @param  array<int, array{invoice_id?: int|null, bill_id?: int|null, amount: float, remark?: string}>  $rows
     */
    public function allocate(int $paymentId, array $rows): int
    {
        $payment = Payment::findOrFail($paymentId);
        $created = 0;

        DB::transaction(function () use ($payment, $rows, &$created) {
            $remaining = $this->paymentRemaining($payment);

            foreach ($rows as $row) {
                $amount = round((float) ($row['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                $invoiceId = isset($row['invoice_id']) && $row['invoice_id'] ? (int) $row['invoice_id'] : null;
                $billId = isset($row['bill_id']) && $row['bill_id'] ? (int) $row['bill_id'] : null;

                if ($payment->direction === 'in' && $billId !== null) {
                    throw new \InvalidArgumentException('收款只能核销客户发票，不能核销供应商账单');
                }
                if ($payment->direction === 'out' && $invoiceId !== null) {
                    throw new \InvalidArgumentException('付款只能核销供应商账单，不能核销客户发票');
                }
                if ($invoiceId === null && $billId === null) {
                    throw new \InvalidArgumentException('核销必须指定发票或账单');
                }

                // 幂等：同一条收付款 + 同一张发票/账单只允许一条明细，重复提交直接跳过（不重复计入、不报错）
                if ($invoiceId !== null
                    && PaymentAllocation::where('payment_id', $payment->id)->where('invoice_id', $invoiceId)->exists()) {
                    continue;
                }
                if ($billId !== null
                    && PaymentAllocation::where('payment_id', $payment->id)->where('bill_id', $billId)->exists()) {
                    continue;
                }

                if ($amount > $remaining + 0.001) {
                    throw new \DomainException('核销金额超过该笔收付款的未核销余额 ' . number_format($remaining, 2));
                }

                if ($invoiceId !== null) {
                    $invoice = CustomerInvoice::withoutGlobalScopes()->findOrFail($invoiceId);
                    $committed = (float) PaymentAllocation::where('invoice_id', $invoiceId)->sum('amount');
                    if ($committed + $amount > (float) $invoice->amount + 0.001) {
                        throw new \DomainException("发票 {$invoice->invoice_no} 核销金额超过开票金额（已核销 " . number_format($committed, 2) . '）');
                    }
                }

                if ($billId !== null) {
                    $bill = SupplierBill::withoutGlobalScopes()->findOrFail($billId);
                    $committed = (float) PaymentAllocation::where('bill_id', $billId)->sum('amount');
                    if ($committed + $amount > (float) $bill->amount + 0.001) {
                        throw new \DomainException("账单 {$bill->bill_no} 核销金额超过应付金额（已核销 " . number_format($committed, 2) . '）');
                    }
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'bill_id' => $billId,
                    'amount' => $amount,
                    'remark' => $row['remark'] ?? null,
                ]);

                $remaining -= $amount;
                $created++;
            }
        });

        return $created;
    }

    /**
     * 按核销明细重算发票/账单的 paid_amount 与状态（幂等，可反复调用）。
     */
    public function apply(int $paymentId): void
    {
        $payment = Payment::findOrFail($paymentId);
        if ($payment->status !== 'executed') {
            return; // 审批中的收付款不计入已收/已付
        }

        $allocations = PaymentAllocation::where('payment_id', $payment->id)->get();

        DB::transaction(function () use ($allocations) {
            foreach ($allocations as $a) {
                if ($a->invoice_id) {
                    $invoice = CustomerInvoice::withoutGlobalScopes()->findOrFail($a->invoice_id);
                    $paid = (float) PaymentAllocation::where('invoice_id', $invoice->id)
                        ->whereHas('payment', fn ($q) => $q->where('status', 'executed'))
                        ->sum('amount');
                    $this->refreshInvoice($invoice, $paid);
                }
                if ($a->bill_id) {
                    $bill = SupplierBill::withoutGlobalScopes()->findOrFail($a->bill_id);
                    $paid = (float) PaymentAllocation::where('bill_id', $bill->id)
                        ->whereHas('payment', fn ($q) => $q->where('status', 'executed'))
                        ->sum('amount');
                    $this->refreshBill($bill, $paid);
                }
            }
        });

        Log::info('财务核销已回写', ['payment_id' => $payment->id, 'allocations' => $allocations->count()]);
    }

    public function paymentRemaining(Payment $payment): float
    {
        return round((float) $payment->amount - (float) PaymentAllocation::where('payment_id', $payment->id)->sum('amount'), 2);
    }

    public function invoiceRemaining(CustomerInvoice $invoice): float
    {
        return round((float) $invoice->amount - (float) $invoice->paid_amount, 2);
    }

    private function refreshInvoice(CustomerInvoice $invoice, float $paid): void
    {
        if (in_array($invoice->status, ['void', 'pending_approval'], true)) {
            return; // 审批中 / 作废不动状态，只记金额
        }

        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= (float) $invoice->amount ? 'paid' : 'partial');

        $invoice->update(['paid_amount' => round($paid, 2), 'status' => $status]);
    }

    private function refreshBill(SupplierBill $bill, float $paid): void
    {
        $status = $paid <= 0 ? 'unpaid' : ($paid + 0.001 >= (float) $bill->amount ? 'paid' : 'partial');

        $bill->update(['paid_amount' => round($paid, 2), 'status' => $status]);
    }
}
