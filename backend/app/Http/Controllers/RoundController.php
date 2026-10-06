<?php

namespace App\Http\Controllers;

use App\Models\ExitOrder;
use App\Models\InspectionPhoto;
use App\Models\InspectionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoundController extends Controller
{
    // 在出场单上追加一轮检查(初检为第 1 轮,复洗后复检依次递增)
    public function store(Request $request, ExitOrder $order): JsonResponse
    {
        $data = $request->validate([
            'result' => 'required|in:pass,fail',
            'note' => 'nullable|string|max:500',
            'photo_ids' => 'required|array|min:2',
            'photo_ids.*' => 'integer|distinct',
        ]);

        return DB::transaction(function () use ($request, $order, $data) {
            $order = ExitOrder::whereKey($order->id)->lockForUpdate()->first();

            if (! $order->isOpen()) {
                return response()->json(['message' => '该出场单已放行,不能再追加检查'], 422);
            }

            $photos = InspectionPhoto::whereIn('id', $data['photo_ids'])->get();

            if ($photos->count() !== count($data['photo_ids'])) {
                return response()->json(['message' => '存在无效的照片'], 422);
            }

            // 关键校验:照片必须属于本出场单 —— 禁止使用以前出场(其他单)的旧照片
            if ($photos->contains(fn ($p) => $p->exit_order_id !== $order->id)) {
                return response()->json(['message' => '不能使用其他出场单的照片,必须现场重新拍照上传'], 422);
            }

            // 每张照片只能用于一轮检查,防止复洗后仍引用初检照片
            if ($photos->contains(fn ($p) => $p->inspection_round_id !== null)) {
                return response()->json(['message' => '照片已被此前的检查轮次使用,请重新拍照上传'], 422);
            }

            $types = $photos->pluck('type')->unique();
            foreach ([InspectionPhoto::TYPE_WHEEL, InspectionPhoto::TYPE_BODY] as $required) {
                if (! $types->contains($required)) {
                    return response()->json(['message' => '每轮检查必须包含车轮和车身照片'], 422);
                }
            }

            $round = $order->rounds()->create([
                'round_no' => ($order->rounds()->max('round_no') ?? 0) + 1,
                'result' => $data['result'],
                'note' => $data['note'] ?? null,
                'inspector_id' => $request->user()->id,
            ]);

            InspectionPhoto::whereIn('id', $data['photo_ids'])
                ->update(['inspection_round_id' => $round->id]);

            return response()->json($round->load('photos'), 201);
        });
    }
}
