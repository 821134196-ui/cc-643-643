<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GatePassController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/gate-passes', [GatePassController::class, 'index']);
    Route::post('/gate-passes', [GatePassController::class, 'store']);
    Route::get('/gate-passes/{gatePass}', [GatePassController::class, 'show']);

    // 识别失败后的人工车牌登记（原识别结果仍保留在出场单上）
    Route::post('/gate-passes/{gatePass}/manual-plate', [GatePassController::class, 'registerManualPlate']);

    // 在原单上增加一轮检查（初次检查或复洗后复查），每轮必须重新上传照片
    Route::post('/gate-passes/{gatePass}/inspections', [GatePassController::class, 'addInspection']);

    // 闸机放行：合格正常放行 / 值班员人工放行
    Route::post('/gate-passes/{gatePass}/release', [GatePassController::class, 'release']);
});
