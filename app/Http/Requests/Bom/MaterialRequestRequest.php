<?php

namespace App\Http\Requests\Bom;

use Illuminate\Foundation\Http\FormRequest;

class MaterialRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'category_code' => ['required', 'string', 'max:32', 'exists:material_categories,category_code'],
        ];
    }
}
