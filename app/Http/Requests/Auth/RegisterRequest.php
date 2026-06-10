<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Họ tên: bắt buộc, tối thiểu 3 ký tự
            'HoTen'                => ['required', 'string', 'min:3', 'max:100'],

            // Email: bắt buộc, đúng định dạng, không được trùng trong bảng user
            'email'                => ['required', 'email:rfc,dns', 'max:100', 'unique:user,email'],

            // Mật khẩu: tối thiểu 6 ký tự, phải xác nhận
//             'matkhau' => [
//     'required',
//     'string',
//     'min:8',
//     'max:50',
//     'confirmed',
//     'regex:/[a-z]/',
//     'regex:/[A-Z]/',
//     'regex:/[0-9]/',
//     'regex:/[@$!%*#?&]/',
// ],
            'matkhau'              => ['required', 'string', 'min:6', 'confirmed'],
            'matkhau_confirmation' => ['required', 'string'],

            // Địa chỉ: tuỳ chọn
            'diachi'               => ['nullable', 'string', 'max:255'],

            // SĐT: chỉ chứa số, đúng 10 chữ số, không được trùng trong bảng user
            'sdt'                  => ['nullable', 'regex:/^[0-9]{10}$/', 'unique:user,sdt'],

            // Role: chỉ cho đăng ký NguoiMua(2) hoặc NguoiBan(3), Admin do hệ thống tạo
            'ID_role'              => ['nullable', 'integer', 'in:2,3', 'exists:role,ID_role'],
        ];
    }

    public function messages(): array
    {
        return [
            'HoTen.required'               => 'Vui lòng nhập họ tên.',
            'HoTen.min'                    => 'Họ tên phải có ít nhất 3 ký tự.',
            'HoTen.max'                    => 'Họ tên không được vượt quá 100 ký tự.',

            'email.required'               => 'Vui lòng nhập email.',
            'email.email'                  => 'Email không đúng định dạng.',
            'email.unique'                 => 'Email này đã được đăng ký.',

            'matkhau.required'             => 'Vui lòng nhập mật khẩu.',
            'matkhau.min'                  => 'Mật khẩu phải có ít nhất 6 ký tự.',
            'matkhau.confirmed'            => 'Xác nhận mật khẩu không khớp.',
            'matkhau_confirmation.required'=> 'Vui lòng xác nhận mật khẩu.',

            'sdt.regex'                    => 'Số điện thoại phải gồm đúng 10 chữ số (0-9).',
            'sdt.unique'                   => 'Số điện thoại này đã được đăng ký.',

            'ID_role.integer'              => 'Role không hợp lệ.',
            'ID_role.in'                   => 'Role không hợp lệ. Chỉ chấp nhận: NguoiMua (2) hoặc NguoiBan (3).',
            'ID_role.exists'               => 'Role không tồn tại trong hệ thống.',
        ];
    }

    /**
     * Xử lý dữ liệu sau khi validation thành công.
     * Chuẩn hóa: gán ID_role mặc định nếu không truyền.
     */
    protected function passedValidation(): void
    {
        // Mặc định là NguoiMua (ID_role = 2) nếu không truyền
        if (! $this->has('ID_role') || $this->ID_role === null) {
            $this->merge(['ID_role' => 2]);
        }
    }
}
