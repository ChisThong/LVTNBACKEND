<?php

namespace App\Http\Requests\DonHang;

use Illuminate\Foundation\Http\FormRequest;

class StoreDonHangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role kiểm tra bởi middleware role:NguoiMua
    }

    public function rules(): array
    {
        return [
            // Địa chỉ giao hàng: bắt buộc
            'DiaChiGiao'   => ['required', 'string', 'min:10', 'max:255'],

            // SĐT nhận hàng: bắt buộc, đúng 10 chữ số
            'SDTNhanHang'  => ['required', 'regex:/^[0-9]{10}$/'],

            // Danh sách sản phẩm: bắt buộc, phải là mảng, ít nhất 1 sản phẩm
            'san_pham'              => ['required', 'array', 'min:1'],
            'san_pham.*.ID_SanPham' => ['required', 'integer', 'exists:sanpham,ID_SanPham'],
            'san_pham.*.SoLuong'    => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'DiaChiGiao.required'  => 'Vui lòng nhập địa chỉ giao hàng.',
            'DiaChiGiao.min'       => 'Địa chỉ giao hàng phải có ít nhất 10 ký tự.',
            'DiaChiGiao.max'       => 'Địa chỉ giao hàng không được vượt quá 255 ký tự.',

            'SDTNhanHang.required' => 'Vui lòng nhập số điện thoại nhận hàng.',
            'SDTNhanHang.regex'    => 'Số điện thoại nhận hàng phải gồm đúng 10 chữ số.',

            'san_pham.required'              => 'Vui lòng chọn ít nhất 1 sản phẩm.',
            'san_pham.array'                 => 'Danh sách sản phẩm không hợp lệ.',
            'san_pham.min'                   => 'Vui lòng chọn ít nhất 1 sản phẩm.',
            'san_pham.*.ID_SanPham.required' => 'ID sản phẩm là bắt buộc.',
            'san_pham.*.ID_SanPham.exists'   => 'Sản phẩm không tồn tại trong hệ thống.',
            'san_pham.*.SoLuong.required'    => 'Số lượng đặt hàng là bắt buộc.',
            'san_pham.*.SoLuong.min'         => 'Số lượng đặt hàng phải ít nhất là 1.',
        ];
    }
}
