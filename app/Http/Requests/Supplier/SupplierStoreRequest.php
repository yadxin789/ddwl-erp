<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class SupplierStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'supplier_code' => ['required', 'string', 'max:32', 'unique:suppliers,supplier_code'],
            'supplier_name' => ['required', 'string', 'max:128'],
            'contact_person' => ['nullable', 'string', 'max:64'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:128'],
            'address' => ['nullable', 'string', 'max:255'],
            'supplier_type' => ['nullable', 'string', 'max:32'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
