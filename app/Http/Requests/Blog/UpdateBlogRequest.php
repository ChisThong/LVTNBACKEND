<?php

namespace App\Http\Requests\Blog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use SebastianBergmann\Type\TrueType;

class UpdateBlogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return True;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tittel'        => 'required|string|max:255',
            'tomtat'        => 'nullable|string',
            'noidung'       => 'required|string',
            'hinhanh'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'ID_TinhThanh'  => 'required|integer'
        ];
    }
    public function messages():array
    {
        return [
            'tittel.required'       => 'Tiêu đề không được để trống.',
            'noidung.required'      => 'Nội dung không được để trống.',
            'ID_TinhThanh.required' => 'Vui lòng chọn Tỉnh thành.',
            'hinhanh.image'         => 'File tải lên phải là hình ảnh.',
            'hinhanh.max'           => 'Kích thước ảnh không được quá 2MB.'
        ];
    }
}

