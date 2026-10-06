<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', 'in:qualified,unqualified'],
            'finding' => ['nullable', 'string', 'max:200'],
            // 每一轮都必须当次重新上传两类照片，不接受任何"引用旧照片"的参数
            'wheel_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'body_photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'wheel_photo.required' => '必须上传本轮拍摄的车轮照片',
            'body_photo.required' => '必须上传本轮拍摄的车身照片',
        ];
    }
}
