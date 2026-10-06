<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    // 本地演示登录:选择预置账号即可,返回该账号固定 token
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => '账号不存在'], 401);
        }

        return response()->json([
            'token' => $user->api_token,
            'user' => $user->only(['id', 'name', 'email', 'role']),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json(User::select('id', 'name', 'email', 'role')->get());
    }
}
