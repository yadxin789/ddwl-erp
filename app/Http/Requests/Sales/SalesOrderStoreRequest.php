<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'quote_id' => ['nullable', 'exists:sales_quotes,id'],
            'order_date' => ['required', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['exists:materials,id'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
            'unit_price.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
