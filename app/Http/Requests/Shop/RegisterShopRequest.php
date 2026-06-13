<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class RegisterShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'TenShop'     => ['required', 'string', 'min:3', 'max:100'],
            'SCCD'        => ['required', 'string', 'max:20'],
            'SoDienThoai' => ['required', 'string', 'max:15'],
            'DiaChi'      => ['required', 'string', 'max:255'],
            'SoTaiKhoang' => ['required', 'string', 'max:50'],
            'TenNganHang' => ['nullable', 'string', 'max:100'],
            'Tittle'      => ['nullable', 'string', 'max:255'],
            'GioiThieu'   => ['nullable', 'string', 'max:2000'],
            'LoaiHinhKinhDoanh' => ['required', 'in:ho_kinh_doanh,doanh_nghiep'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenShop.required'     => 'Vui lòng nhập tên gian hàng.',
            'TenShop.min'          => 'Tên gian hàng phải có ít nhất 3 ký tự.',
            'SCCD.required'        => 'Vui lòng nhập số CCCD/CMND.',
            'SoDienThoai.required' => 'Vui lòng nhập số điện thoại.',
            'SoDienThoai.max'      => 'Số điện thoại không hợp lệ.',
            'DiaChi.required'      => 'Vui lòng nhập địa chỉ gian hàng.',
            'SoTaiKhoang.required' => 'Vui lòng nhập số tài khoản ngân hàng.',
        ];
    }
}
