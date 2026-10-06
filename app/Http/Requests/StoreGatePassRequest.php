<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGatePassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // 车牌识别相机抓拍照（本地模拟识别时使用）
            'lpr_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            // 联调时显式模拟识别失败
            'simulate_failure' => ['nullable', 'boolean'],
            // 仅识别失败场景下使用的人工登记车牌
            'manual_plate' => ['nullable', 'string', 'max:20'],
            'driver_name' => ['nullable', 'string', 'max:50'],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }
}
