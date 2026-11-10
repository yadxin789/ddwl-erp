<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 登录路由本身在 guest 中间件下，未登录是预期状态，必须放行
        return true;
    }

    public function rules(): array
    {
        return [
            'account' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'account.required' => '请输入账号或邮箱',
            'password.required' => '请输入密码',
        ];
    }
}
