<?php

namespace App\Services;

/**
 * 车牌识别接口(本地模拟)。
 * 真实环境替换为摄像头/识别厂商 SDK 调用。
 */
class PlateRecognizer
{
    private const DEMO_PLATES = ['沪A12345', '苏E8T666', '浙B3D521', '皖K99Q07', '鲁Q5W888'];

    /**
     * @return array{status: string, plate: ?string}
     */
    public function recognize(bool $forceFail = false): array
    {
        // 模拟识别:约 1/4 概率失败,返回设备原始(可能为空/残缺)结果
        $failed = $forceFail || random_int(1, 4) === 1;

        if ($failed) {
            return [
                'status' => 'failed',
                // 设备失败时也可能返回残缺字符串,原样保留
                'plate' => random_int(0, 1) ? null : '沪?1??45',
            ];
        }

        return [
            'status' => 'ok',
            'plate' => self::DEMO_PLATES[array_rand(self::DEMO_PLATES)],
        ];
    }
}
