<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'remark' => ['nullable', 'string', 'max:255'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['exists:materials,id'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
            'unit_price.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
