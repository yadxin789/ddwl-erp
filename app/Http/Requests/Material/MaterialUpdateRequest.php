<?php

namespace App\Http\Requests\Material;

use Illuminate\Foundation\Http\FormRequest;

class MaterialUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'category_code' => ['required', 'exists:material_categories,category_code'],
            'subcategory_code' => ['nullable', 'exists:material_subcategories,subcategory_code'],
            'name' => ['required', 'string', 'max:128'],
            'spec' => ['required', 'string', 'max:256'],
            'package' => ['nullable', 'string', 'max:32'],
            'unit' => ['required', 'string', 'max:8'],
            'rohs_status' => ['nullable', 'integer', 'in:-1,0,1'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:0,1'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
