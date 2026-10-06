<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\PlateController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\RoundController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::get('/users', [AuthController::class, 'users']);

Route::middleware('token.auth')->group(function () {
    Route::post('/plate/recognize', [PlateController::class, 'recognize']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::post('/orders/{order}/photos', [PhotoController::class, 'store']);
    Route::post('/orders/{order}/rounds', [RoundController::class, 'store']);

    Route::post('/orders/{order}/release', [ReleaseController::class, 'release']);
    Route::post('/orders/{order}/manual-release', [ReleaseController::class, 'manualRelease'])
        ->middleware('supervisor');
});
