<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesReturnStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'return_reason' => ['nullable', 'string', 'max:255'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['exists:materials,id'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
