<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSupervisor()) {
            return response()->json(['message' => '无权限:人工放行需由值班员确认'], 403);
        }

        return $next($request);
    }
}
