<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'TenShop'     => ['sometimes', 'string', 'min:3', 'max:100'],
            'DiaChi'      => ['sometimes', 'string', 'max:255'],
            'TenNganHang' => ['nullable', 'string', 'max:100'],
            'SoTaiKhoang' => ['nullable', 'string', 'max:50'],
            'Tittle'      => ['nullable', 'string', 'max:255'],
            'GioiThieu'   => ['nullable', 'string', 'max:2000'],
            // Upload logo/banner
            'logo'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'baner'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'TenShop.min'    => 'Tên gian hàng phải có ít nhất 3 ký tự.',
            'logo.image'     => 'Logo phải là file hình ảnh.',
            'logo.max'       => 'Logo không được vượt quá 2MB.',
            'baner.image'    => 'Banner phải là file hình ảnh.',
            'baner.max'      => 'Banner không được vượt quá 4MB.',
        ];
    }
}
