<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Dùng 'sometimes' — chỉ validate nếu field được gửi lên
            'TenSanPham'   => ['sometimes', 'string', 'min:3', 'max:200'],
            'Tittle'       => ['nullable', 'string', 'max:255'],
            'MoTa'         => ['nullable', 'string', 'max:5000'],
            'NguonGoc'     => ['nullable', 'string', 'max:255'],
            'Gia'          => ['sometimes', 'numeric', 'gt:0'],
            'SoLuongTon'   => ['sometimes', 'integer', 'min:0'],
            'TrangThai'    => ['sometimes', 'integer', 'in:0,1'],
            'Donvi'        => ['nullable', 'string', 'max:50'],
            'ID_Shop'      => ['sometimes', 'integer', 'exists:shop,ID_Shop'],
            'ID_PhanLoai'  => ['sometimes', 'integer', 'exists:phanloaisp,ID_PhanLoai'],
            'ID_TinhThanh' => ['nullable', 'integer'],

            // Upload thêm ảnh
            'hinh_anh'     => ['nullable', 'array'],
            'hinh_anh.*'   => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            // Xoá ảnh theo ID
            'xoa_hinh_anh'   => ['nullable', 'array'],
            'xoa_hinh_anh.*' => ['integer', 'exists:hinhanh,ID_HinhAnh'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenSanPham.min'       => 'Tên sản phẩm phải có ít nhất 3 ký tự.',
            'Gia.numeric'          => 'Giá phải là số.',
            'Gia.gt'               => 'Giá sản phẩm phải lớn hơn 0.',
            'SoLuongTon.integer'   => 'Số lượng tồn kho phải là số nguyên.',
            'SoLuongTon.min'       => 'Số lượng tồn kho không được âm.',
            'ID_Shop.exists'       => 'Shop không tồn tại trong hệ thống.',
            'ID_PhanLoai.exists'   => 'Phân loại sản phẩm không tồn tại.',
            'TrangThai.in'         => 'Trạng thái chỉ được là 0 (ẩn) hoặc 1 (hiển thị).',
            'hinh_anh.*.image'     => 'File tải lên phải là hình ảnh.',
            'hinh_anh.*.mimes'     => 'Hình ảnh phải có định dạng: jpg, jpeg, png hoặc webp.',
            'hinh_anh.*.max'       => 'Mỗi hình ảnh không được vượt quá 5MB.',
            'xoa_hinh_anh.*.exists'=> 'Hình ảnh cần xoá không tồn tại.',
        ];
    }
}
