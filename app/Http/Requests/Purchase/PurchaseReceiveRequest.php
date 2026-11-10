<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseReceiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'po_id' => ['required', 'exists:purchase_orders,id'],
            'receipt_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'exists:warehouse_locations,id'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['exists:materials,id'],
            'lot_no.*' => ['required', 'string', 'max:32'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
            'supplier_batch.*' => ['nullable', 'string', 'max:64'],
        ];
    }
}
