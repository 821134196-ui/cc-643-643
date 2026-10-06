<?php

namespace App\Http\Controllers;

use App\Models\ExitOrder;
use App\Models\GateCommand;
use App\Services\GateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReleaseController extends Controller
{
    // 正常放行:只有本次出场最后一轮检查合格才允许发送闸机指令
    public function release(Request $request, ExitOrder $order, GateService $gate): JsonResponse
    {
        return DB::transaction(function () use ($request, $order, $gate) {
            $order = ExitOrder::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->isOpen()) {
                return response()->json(['message' => '该出场单已放行,请勿重复操作'], 422);
            }

            if (! $order->canAutoRelease()) {
                $latest = $order->latestRound;
                $message = $latest === null
                    ? '该车辆尚未完成任何洗轮检查,禁止放行'
                    : '最后一轮检查不合格(第 '.$latest->round_no.' 轮),请返回洗轮区复洗后重新检查';

                return response()->json(['message' => $message], 422);
            }

            $command = $gate->open($order, $request->user(), GateCommand::TYPE_AUTO);
            $order->update(['status' => ExitOrder::STATUS_RELEASED]);

            return response()->json(['order' => $order->fresh(), 'command' => $command]);
        });
    }

    // 人工放行:仅值班员(路由中间件保证),必须填写原因
    public function manualRelease(Request $request, ExitOrder $order, GateService $gate): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|min:2|max:500',
        ], [
            'reason.required' => '人工放行必须填写原因',
        ]);

        return DB::transaction(function () use ($request, $order, $gate, $data) {
            $order = ExitOrder::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->isOpen()) {
                return response()->json(['message' => '该出场单已放行,请勿重复操作'], 422);
            }

            $command = $gate->open($order, $request->user(), GateCommand::TYPE_MANUAL, $data['reason']);
            $order->update(['status' => ExitOrder::STATUS_RELEASED_MANUAL]);

            return response()->json(['order' => $order->fresh(), 'command' => $command]);
        });
    }
}
