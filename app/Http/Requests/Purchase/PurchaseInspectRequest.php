<?php

namespace App\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseInspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'exists:goods_receipt_lines,id'],
            'lines.*.qualified_qty' => ['required', 'numeric', 'min:0'],
            'lines.*.rejected_qty' => ['required', 'numeric', 'min:0'],
        ];
    }
}
