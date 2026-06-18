<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Quyền kiểm tra bởi middleware role:Admin,NguoiBan
    }

    public function rules(): array
    {
        return [
            // Tên sản phẩm: bắt buộc, tối thiểu 3 ký tự
            'TenSanPham'   => ['required', 'string', 'min:3', 'max:200'],

            // Tiêu đề (slug/title ngắn): tuỳ chọn
            'Tittle'       => ['nullable', 'string', 'max:255'],

            // Mô tả chi tiết
            'MoTa'         => ['nullable', 'string', 'max:5000'],

            // Nguồn gốc / xuất xứ sản phẩm
            'NguonGoc'     => ['nullable', 'string', 'max:255'],

            // Giá: bắt buộc, số, phải > 0
            'Gia'          => ['required', 'numeric', 'gt:0'],

            // Số lượng tồn kho: bắt buộc, số nguyên, >= 0
            'SoLuongTon'   => ['required', 'integer', 'min:0'],

            // Trạng thái: 1=đang bán, 0=ẩn
            'TrangThai'    => ['nullable', 'integer', 'in:0,1'],

            // Đơn vị tính (kg, hộp, chai,...)
            'Donvi'        => ['nullable', 'string', 'max:50'],

            // Shop: bắt buộc, phải tồn tại trong bảng shop
            'ID_Shop'      => ['required', 'integer', 'exists:shop,ID_Shop'],

            // Phân loại: bắt buộc, phải tồn tại trong bảng phanloaisp
            'ID_PhanLoai'  => ['required', 'integer', 'exists:phanloaisp,ID_PhanLoai'],

            // Tỉnh/thành: tuỳ chọn
            'ID_TinhThanh' => ['nullable', 'integer'],

            // Hình ảnh: có thể upload nhiều ảnh, giới hạn tối đa 5 ảnh
            'hinh_anh'          => ['nullable', 'array', 'max:5'],
            'hinh_anh.*'        => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenSanPham.required'  => 'Vui lòng nhập tên sản phẩm.',
            'TenSanPham.min'       => 'Tên sản phẩm phải có ít nhất 3 ký tự.',
            'TenSanPham.max'       => 'Tên sản phẩm không được vượt quá 200 ký tự.',

            'Gia.required'         => 'Vui lòng nhập giá sản phẩm.',
            'Gia.numeric'          => 'Giá phải là số.',
            'Gia.gt'               => 'Giá sản phẩm phải lớn hơn 0.',

            'SoLuongTon.required'  => 'Vui lòng nhập số lượng tồn kho.',
            'SoLuongTon.integer'   => 'Số lượng tồn kho phải là số nguyên.',
            'SoLuongTon.min'       => 'Số lượng tồn kho không được âm.',

            'ID_Shop.required'     => 'Vui lòng chọn Shop.',
            'ID_Shop.exists'       => 'Shop không tồn tại trong hệ thống.',

            'ID_PhanLoai.required' => 'Vui lòng chọn phân loại sản phẩm.',
            'ID_PhanLoai.exists'   => 'Phân loại sản phẩm không tồn tại.',

            'TrangThai.in'         => 'Trạng thái chỉ được là 0 (ẩn) hoặc 1 (hiển thị).',

            'hinh_anh.max'         => 'Chỉ được tải lên tối đa 5 hình ảnh.',
            'hinh_anh.*.image'     => 'File tải lên phải là hình ảnh.',
            'hinh_anh.*.mimes'     => 'Hình ảnh phải có định dạng: jpg, jpeg, png hoặc webp.',
            'hinh_anh.*.max'       => 'Mỗi hình ảnh không được vượt quá 5MB.',
        ];
    }
}
