<?php

namespace App\Http\Requests\Map;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMapRequest extends FormRequest
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
            'PhanLoai' => 'required|string',
            'TenDacSan' => 'required|string|max:100',
            'MoTa' => 'required|string',
            'ViDo' => 'required|numeric',
            'KinhDo' => 'required|numeric',
            'HinhAnh' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'ID_TinhThanh' => 'required',
            'ID_Xa' => 'nullable',
            'ID_Ap'=>'nullable'
        
        ];
    }
    public function message(): array
    {
        return [
            'PhanLoai.required'  => 'Vui lòng chọn phân loại.',
            'TenDacSan.required' => 'Tên đặc sản không được để trống.',
            'TenDacSan.max'      => 'Tên đặc sản quá dài (tối đa 100 ký tự).',
            'MoTa.required'      => 'Vui lòng nhập mô tả chi tiết.',
            'ViDo.required'      => 'Vĩ độ không được để trống.',
            'ViDo.numeric'       => 'Vĩ độ phải là một số hợp lệ.',
            'KinhDo.required'    => 'Kinh độ không được để trống.',
            'KinhDo.numeric'     => 'Kinh độ phải là một số hợp lệ.',
            'HinhAnh.image'      => 'File tải lên phải là định dạng hình ảnh.',
            'HinhAnh.max'        => 'Kích thước ảnh không được vượt quá 2MB.',
            'ID_TinhThanh.required' => 'Tỉnh thành không được để trống',
        ];
    }
}
