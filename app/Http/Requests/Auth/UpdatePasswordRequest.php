<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => '请输入当前密码',
            'password.required' => '请输入新密码',
            'password.min' => '新密码至少6位',
            'password.confirmed' => '两次密码输入不一致',
        ];
    }
}
