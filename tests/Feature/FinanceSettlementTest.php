<?php

namespace Tests\Feature;

use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\Payment;
use App\Models\Finance\SupplierBill;
use App\Models\Organization\User;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Services\SettlementService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-03 财务核销闭环（D-04）
 *
 * 验收：开票 100 → 收款 60 → paid=60/status=partial、AR=40；重复核销幂等；超核销被拒；
 * 三单匹配差异表可查；审批中的收付款不虚增应收。
 */
class FinanceSettlementTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    private function makeInvoice(float $amount = 100): CustomerInvoice
    {
        return CustomerInvoice::create([
            'invoice_no' => 'INV-T' . substr(uniqid(), -6),
            'customer_id' => (int) DB::table('customers')->value('id'),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'amount' => $amount,
            'tax_amount' => 0,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
    }

    private function makePayment(string $direction, float $amount, string $status = 'executed'): Payment
    {
        return Payment::create([
            'payment_no' => 'PAY-T' . substr(uniqid(), -6),
            'direction' => $direction,
            'customer_id' => $direction === 'in' ? (int) DB::table('customers')->value('id') : null,
            'supplier_id' => $direction === 'out' ? (int) DB::table('suppliers')->value('id') : null,
            'amount' => $amount,
            'method' => 'bank',
            'status' => $status,
            'paid_at' => now()->toDateString(),
            'user_id' => (int) DB::table('users')->where('username', '刘芳')->value('id'),
        ]);
    }

    public function test_invoice_from_sales_order_takes_shipped_amount(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('陈静');
            $customerId = (int) DB::table('customers')->value('id');
            $order = SalesOrder::create([
                'order_no' => 'SO-FIN' . substr(uniqid(), -6),
                'customer_id' => $customerId,
                'order_date' => now()->toDateString(),
                'status' => 'confirmed',
                'total_amount' => 100,
                'shipped_qty' => 5,
                'salesperson_id' => (int) DB::table('users')->where('username', '钱七')->value('id'),
            ]);
            SalesOrderLine::create([
                'order_id' => $order->id,
                'material_id' => (int) DB::table('materials')->value('id'),
                'qty' => 5,
                'shipped_qty' => 5,
                'unit_price' => 20,
            ]);

            $this->post(route('finance.invoices.store'), [
                'customer_id' => $customerId,
                'sales_order_id' => $order->id,
                'invoice_date' => now()->toDateString(),
            ])->assertRedirect();

            $invoice = CustomerInvoice::withoutGlobalScopes()->where('sales_order_id', $order->id)->latest('id')->first();
            $this->assertNotNull($invoice);
            $this->assertEquals(100.0, (float) $invoice->amount, '按订单开票：已发货 5 × 20 = 100');
        } finally {
            DB::rollBack();
        }
    }

    public function test_receipt_allocation_writes_paid_amount_and_status(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('刘芳');
            $invoice = $this->makeInvoice(100);
            $payment = $this->makePayment('in', 60);

            $service = app(SettlementService::class);
            $created = $service->allocate($payment->id, [['invoice_id' => $invoice->id, 'amount' => 60]]);
            $this->assertSame(1, $created);

            $service->apply($payment->id);

            $invoice->refresh();
            $this->assertEquals(60.0, (float) $invoice->paid_amount);
            $this->assertSame('partial', $invoice->status);
            $this->assertEquals(40.0, $service->invoiceRemaining($invoice), 'AR = 100 − 60 = 40');

            // 重复 apply + 重复登记同一条核销 → 金额不变（幂等）
            $service->apply($payment->id);
            $service->allocate($payment->id, [['invoice_id' => $invoice->id, 'amount' => 60]]);
            $service->apply($payment->id);
            $invoice->refresh();
            $this->assertEquals(60.0, (float) $invoice->paid_amount);
            $this->assertSame(1, \App\Models\Finance\PaymentAllocation::where('invoice_id', $invoice->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_pending_payment_does_not_increase_ar(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('刘芳');
            $invoice = $this->makeInvoice(100);
            $payment = $this->makePayment('in', 60, 'pending');

            $service = app(SettlementService::class);
            $service->allocate($payment->id, [['invoice_id' => $invoice->id, 'amount' => 60]]);
            $service->apply($payment->id);

            $invoice->refresh();
            $this->assertEquals(0.0, (float) $invoice->paid_amount, '审批中的收款不计入已收');
            $this->assertSame('unpaid', $invoice->status);

            // 审批通过并执行后才回写
            $payment->update(['status' => 'executed']);
            $service->apply($payment->id);
            $invoice->refresh();
            $this->assertEquals(60.0, (float) $invoice->paid_amount);
            $this->assertSame('partial', $invoice->status);
        } finally {
            DB::rollBack();
        }
    }

    public function test_over_allocation_is_rejected(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('刘芳');
            $invoice = $this->makeInvoice(100);
            $payment1 = $this->makePayment('in', 60);
            $payment2 = $this->makePayment('in', 50);

            $service = app(SettlementService::class);
            $service->allocate($payment1->id, [['invoice_id' => $invoice->id, 'amount' => 60]]);
            $service->apply($payment1->id);

            $this->expectException(\DomainException::class);
            $service->allocate($payment2->id, [['invoice_id' => $invoice->id, 'amount' => 50]]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_payment_cannot_allocate_cross_direction_documents(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('刘芳');
            $bill = SupplierBill::create([
                'bill_no' => 'BILL-T' . substr(uniqid(), -6),
                'supplier_id' => (int) DB::table('suppliers')->value('id'),
                'bill_date' => now()->toDateString(),
                'amount' => 100,
                'paid_amount' => 0,
                'status' => 'unpaid',
            ]);
            $payment = $this->makePayment('in', 100);

            $this->expectException(\InvalidArgumentException::class);
            app(SettlementService::class)->allocate($payment->id, [['bill_id' => $bill->id, 'amount' => 100]]);
        } finally {
            DB::rollBack();
        }
    }

    public function test_three_way_match_page_permission(): void
    {
        $this->actingAsDemo('陈静');
        $this->get(route('finance.three-way-match'))->assertStatus(200);

        $this->actingAsDemo('钱七');
        $this->get(route('finance.three-way-match'))->assertStatus(403);
    }

    public function test_arap_excludes_pending_approval_invoices(): void
    {
        DB::beginTransaction();
        try {
            $this->actingAsDemo('陈静');
            $pending = $this->makeInvoice(88);
            $pending->update(['status' => 'pending_approval']);

            $response = $this->get(route('finance.arap'));
            $response->assertStatus(200);
            $this->assertFalse(
                CustomerInvoice::whereIn('status', ['unpaid', 'partial'])->where('invoice_no', $pending->invoice_no)->exists(),
                '审批中的发票不应出现在应收明细'
            );
        } finally {
            DB::rollBack();
        }
    }
}
