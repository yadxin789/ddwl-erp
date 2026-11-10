<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class ProductionIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'material_id' => ['required', 'array', 'min:1'],
            'material_id.*' => ['required', 'integer', 'exists:materials,id'],
            'qty' => ['required', 'array', 'min:1'],
            'qty.*' => ['required', 'numeric', 'gt:0'],
            'lot_no' => ['nullable', 'array'],
            'lot_no.*' => ['nullable', 'string', 'max:64'],
        ];
    }
}
