<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'otp_code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => 'Vui lòng nhập email.',
            'email.email'       => 'Email không đúng định dạng.',
            'otp_code.required' => 'Vui lòng nhập mã OTP.',
            'otp_code.size'     => 'Mã OTP phải gồm đúng 6 chữ số.',
            'otp_code.regex'    => 'Mã OTP chỉ được chứa chữ số.',
        ];
    }
}
