<?php

namespace App\Http\Requests\Blog;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;


class StoreBlogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tittel'       => 'required|string|max:255|unique:Blog,tittel',
            'tomtat'       => 'required|string',
            'noidung'      => 'required|string',
            'hinhanh'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'ID_TinhThanh' => 'required|integer',
            'LoaiTin'     => 'required|in:0,1', 
            'video_url'    => 'nullable|url|max:255'
        ];
    }
    public function messages():array
    {
        return [
            'tittel.required'       => 'Tiêu đề không được để trống',
            'tittel.unique'         => 'Tiêu đề đã tồn tại',
            'tomtat.required'       => 'Tóm tắt không được để trống',
            'noidung.required'      => 'Nội dung không được để trống',
            'hinhanh.image'         => 'File tải lên phải là hình ảnh.',
            'hinhanh.max'           => 'Kích thước ảnh không được quá 2MB.',
            'ID_TinhThanh.required' => 'ID Tỉnh Thành không được để trống',
            'LoaiTin.required'     => 'Vui lòng chọn loại tin tức (Sản vật hoặc Lễ hội)',
            'LoaiTin.in'           => 'Loại tin tức không hợp lệ',
            'video_url.url'         => 'Đường dẫn video không đúng định dạng URL (ví dụ: https://youtube.com/...)',
            'video_url.max'         => 'Đường dẫn video không được vượt quá 255 ký tự.',
        ];
    }
}
