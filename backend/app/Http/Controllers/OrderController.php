<?php

namespace App\Http\Controllers;

use App\Models\ExitOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = ExitOrder::with(['latestRound', 'creator:id,name'])
            ->withCount('rounds')
            ->latest('id')
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_number' => 'required|string|max:20',
            'plate_source' => 'required|in:recognized,manual',
            // 人工登记时必须带上识别设备的原始结果(允许为 null,但字段必须提交)
            'plate_recognized' => 'required_if:plate_source,manual|nullable|string|max:20',
        ], [
            'plate_recognized.required_if' => '人工登记时必须保存车牌识别的原始结果',
        ]);

        $order = ExitOrder::create([
            ...$data,
            'plate_recognized' => $data['plate_source'] === 'manual'
                ? $data['plate_recognized']
                : $data['plate_number'],
            'status' => ExitOrder::STATUS_OPEN,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($order, 201);
    }

    public function show(ExitOrder $order): JsonResponse
    {
        $order->load([
            'rounds.photos',
            'rounds.inspector:id,name',
            'creator:id,name',
            'gateCommands.issuer:id,name',
        ]);

        return response()->json([
            ...$order->toArray(),
            'can_auto_release' => $order->canAutoRelease(),
        ]);
    }
}
