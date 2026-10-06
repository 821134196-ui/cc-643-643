<?php

namespace App\Services;

use App\Models\ExitOrder;
use App\Models\GateCommand;
use App\Models\User;

/**
 * 闸机接口(本地模拟)。
 * 真实环境替换为道闸控制器 HTTP/串口指令。
 */
class GateService
{
    public function open(ExitOrder $order, User $issuer, string $type, ?string $reason = null): GateCommand
    {
        // 模拟闸机应答
        $ack = sprintf('GATE-1 ACK OPEN order=%d plate=%s', $order->id, $order->plate_number);

        return $order->gateCommands()->create([
            'type' => $type,
            'reason' => $reason,
            'issued_by' => $issuer->id,
            'gate_response' => $ack,
        ]);
    }
}
