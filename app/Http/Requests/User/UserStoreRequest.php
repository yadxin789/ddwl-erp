<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:32', 'unique:users,username'],
            'name' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:128', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'status' => ['required', 'in:0,1'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }
}
