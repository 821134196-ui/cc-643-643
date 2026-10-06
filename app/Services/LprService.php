<?php

namespace App\Services;

/**
 * 车牌识别（LPR）本地模拟。
 *
 * 真实部署中这里会调用现场相机/识别盒子 SDK；当前环境用确定性伪识别代替：
 * - 识别成功：从识别原图的 sha256 派生一个稳定的车牌号（同一张图永远识别成同一车牌）；
 * - 识别失败：调用方显式要求模拟失败，或上传文件名带 FAIL 标记时，返回失败结果，
 *   原始返回内容仍会原样落库，便于事后追溯，不允许被人工登记覆盖。
 */
class LprService
{
    private const PROVINCES = ['京', '津', '冀', '晋', '蒙', '辽', '吉', '黑', '沪', '苏', '浙', '皖', '闽', '赣', '鲁', '豫', '鄂', '湘', '粤', '川'];
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const ALNUM = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789';

    public function recognize(?string $imagePath, ?string $originalName = null, bool $forceFailure = false): array
    {
        $requestId = 'LPR-' . strtoupper(bin2hex(random_bytes(6)));
        $simulated = true;

        if ($forceFailure || ($originalName !== null && str_contains(strtoupper($originalName), 'FAIL'))) {
            return [
                'status' => 'failed',
                'plate' => null,
                'confidence' => null,
                'raw' => [
                    'simulated' => $simulated,
                    'request_id' => $requestId,
                    'return_code' => 'E_PLATE_NOT_FOUND',
                    'message' => '未检测到清晰车牌（模拟识别失败）',
                    'image_sha256' => $imagePath ? hash_file('sha256', $imagePath) : null,
                ],
            ];
        }

        $hash = $imagePath ? hash_file('sha256', $imagePath) : bin2hex(random_bytes(32));

        // 用哈希各字节派生车牌，保证同一图片识别结果稳定
        $pick = function (int $offset, string $alphabet) use ($hash) {
            return $alphabet[hexdec(substr($hash, $offset * 2, 2)) % strlen($alphabet)];
        };

        $plate = self::PROVINCES[hexdec(substr($hash, 0, 2)) % count(self::PROVINCES)]
            . $pick(1, self::LETTERS)
            . $pick(2, self::ALNUM)
            . $pick(3, self::ALNUM)
            . $pick(4, self::ALNUM)
            . $pick(5, self::ALNUM)
            . $pick(6, self::ALNUM);

        $confidence = 88.0 + (hexdec(substr($hash, 14, 2)) % 1200) / 100; // 88.00 - 99.99

        return [
            'status' => 'success',
            'plate' => $plate,
            'confidence' => round($confidence, 2),
            'raw' => [
                'simulated' => $simulated,
                'request_id' => $requestId,
                'return_code' => 'OK',
                'engine' => 'fake-lpr/1.0',
                'image_sha256' => $hash,
            ],
        ];
    }
}
