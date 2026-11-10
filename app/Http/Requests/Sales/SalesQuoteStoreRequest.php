<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesQuoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'quote_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'remark' => ['nullable', 'string', 'max:255'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['exists:materials,id'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
            'unit_price.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
