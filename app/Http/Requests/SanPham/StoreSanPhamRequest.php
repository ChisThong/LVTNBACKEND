<?php

namespace App\Http\Requests\SanPham;

use Illuminate\Foundation\Http\FormRequest;

class StoreSanPhamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role đã được kiểm tra bởi middleware role:NguoiBan
    }

    public function rules(): array
    {
        return [
            // Tên sản phẩm: bắt buộc
            'TenSP'    => ['required', 'string', 'min:3', 'max:200'],

            // Mô tả: tuỳ chọn
            'MoTa'     => ['nullable', 'string', 'max:5000'],

            // Giá: bắt buộc, phải là số, phải > 0, không âm
            'Gia'      => ['required', 'numeric', 'min:0.01'],

            // Số lượng: bắt buộc, số nguyên, >= 0, không âm
            'SoLuong'  => ['required', 'integer', 'min:0'],

            // Hình ảnh: tuỳ chọn, phải là ảnh hợp lệ, tối đa 5MB
            'HinhAnh'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenSP.required'   => 'Vui lòng nhập tên sản phẩm.',
            'TenSP.min'        => 'Tên sản phẩm phải có ít nhất 3 ký tự.',
            'TenSP.max'        => 'Tên sản phẩm không được vượt quá 200 ký tự.',

            'Gia.required'     => 'Vui lòng nhập giá sản phẩm.',
            'Gia.numeric'      => 'Giá sản phẩm phải là số.',
            'Gia.min'          => 'Giá sản phẩm phải lớn hơn 0. Không cho phép giá âm hoặc bằng 0.',

            'SoLuong.required' => 'Vui lòng nhập số lượng.',
            'SoLuong.integer'  => 'Số lượng phải là số nguyên.',
            'SoLuong.min'      => 'Số lượng không được âm (tối thiểu là 0).',

            'HinhAnh.image'    => 'File tải lên phải là hình ảnh.',
            'HinhAnh.mimes'    => 'Hình ảnh phải có định dạng: jpg, jpeg, png hoặc webp.',
            'HinhAnh.max'      => 'Hình ảnh không được vượt quá 5MB.',
        ];
    }
}
