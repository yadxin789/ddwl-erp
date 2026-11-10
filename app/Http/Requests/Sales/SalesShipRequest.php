<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesShipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'shipment_date' => ['required', 'date'],
            'logistics_company' => ['nullable', 'string', 'max:64'],
            'tracking_no' => ['nullable', 'string', 'max:64'],
        ];
    }
}
