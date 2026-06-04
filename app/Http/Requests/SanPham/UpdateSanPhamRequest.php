<?php

namespace App\Http\Requests\SanPham;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSanPhamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'TenSP'    => ['sometimes', 'string', 'min:3', 'max:200'],
            'MoTa'     => ['nullable', 'string', 'max:5000'],
            'Gia'      => ['sometimes', 'numeric', 'min:0.01'],   // Giá > 0, không âm
            'SoLuong'  => ['sometimes', 'integer', 'min:0'],      // Số lượng >= 0, không âm
            'HinhAnh'  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'TrangThai'=> ['sometimes', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenSP.min'        => 'Tên sản phẩm phải có ít nhất 3 ký tự.',
            'Gia.numeric'      => 'Giá sản phẩm phải là số.',
            'Gia.min'          => 'Giá sản phẩm phải lớn hơn 0. Không cho phép giá âm.',
            'SoLuong.integer'  => 'Số lượng phải là số nguyên.',
            'SoLuong.min'      => 'Số lượng không được âm.',
            'TrangThai.in'     => 'Trạng thái chỉ được là 0 (ẩn) hoặc 1 (hiển thị).',
        ];
    }
}
