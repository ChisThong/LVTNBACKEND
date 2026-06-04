<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'   => ['required', 'email'],
            'matkhau' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'   => 'Vui lòng nhập email.',
            'email.email'      => 'Email không hợp lệ.',
            'matkhau.required' => 'Vui lòng nhập mật khẩu.',
        ];
    }
}
