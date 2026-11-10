<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class CustomerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:32', 'unique:customers,customer_code'],
            'customer_name' => ['required', 'string', 'max:128'],
            'contact_person' => ['nullable', 'string', 'max:64'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:128'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
