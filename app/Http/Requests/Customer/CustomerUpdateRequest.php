<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'customer_code' => ['required', 'string', 'max:32', Rule::unique('customers', 'customer_code')->ignore($this->route('customer')->id)],
            'customer_name' => ['required', 'string', 'max:128'],
            'contact_person' => ['nullable', 'string', 'max:64'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:128'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'payment_terms' => ['nullable', 'string', 'max:64'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ];
    }
}
