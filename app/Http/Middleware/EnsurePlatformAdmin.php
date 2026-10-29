<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($request->user()->is_platform_admin !== true) {
            return response()->json([
                'message' => 'Esta operación requiere administrador de plataforma.',
            ], 403);
        }

        return $next($request);
    }
}
