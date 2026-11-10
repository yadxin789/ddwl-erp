<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:bank,cash,alipay,wechat'],
            'paid_at' => ['required', 'date'],
            'ref_no' => ['nullable', 'string', 'max:64'],
            // direction=in（收款）时必须有合法 customer_id；direction=out（付款）时必须有合法 supplier_id
            'customer_id' => ['required_if:direction,in', 'nullable', 'exists:customers,id'],
            'supplier_id' => ['required_if:direction,out', 'nullable', 'exists:suppliers,id'],
            // P1-03：登记时可直接核销（收款→发票，付款→账单），金额默认全额
            'invoice_id' => ['nullable', 'exists:customer_invoices,id'],
            'bill_id' => ['nullable', 'exists:supplier_bills,id'],
            'allocate_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
