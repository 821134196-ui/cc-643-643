<?php

namespace App\Http\Controllers;

use App\Services\PlateRecognizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlateController extends Controller
{
    // 车牌识别(本地模拟);?fail=1 可强制失败,便于演示人工登记流程
    public function recognize(Request $request, PlateRecognizer $recognizer): JsonResponse
    {
        return response()->json($recognizer->recognize($request->boolean('fail')));
    }
}
