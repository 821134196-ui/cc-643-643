<?php

namespace App\Services;

use App\Models\GatePass;

/**
 * 闸机接口本地模拟。
 *
 * 真实部署中这里向道闸控制器下发开闸指令并等待应答；当前环境只做确定性模拟，
 * 返回指令编号与应答码。任何放行都必须先通过出场业务校验，本服务不做业务判断。
 */
class GateService
{
    public function open(GatePass $gatePass): array
    {
        $commandId = sprintf('GATE-%d-%s', $gatePass->id, now()->format('YmdHis'));

        $response = [
            'command_id' => $commandId,
            'code' => 'OK',
            'message' => '闸机已抬起（模拟）',
            'lane' => '1',
            'plate' => $gatePass->effectivePlate(),
            'answered_at' => now()->toDateTimeString(),
        ];

        return [
            'command_id' => $commandId,
            'code' => 'OK',
            'raw' => $response,
        ];
    }
}
