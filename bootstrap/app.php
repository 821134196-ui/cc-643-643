<?php

use App\Exceptions\GatePassRuleException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 业务规则违反（最后一轮不合格、旧照片冒充等）统一 422 + 错误码
        $exceptions->render(function (GatePassRuleException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error_code' => $e->errorCode,
                ], 422);
            }

            return null;
        });

        // 权限不足（如普通录入人员尝试人工放行）统一 403
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: '无操作权限',
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }

            return null;
        });
    })->create();
