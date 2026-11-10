<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StocktakeAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'actual' => ['required', 'array', 'min:1'],
            'actual.*' => ['required', 'numeric', 'min:0'],
        ];
    }
}
