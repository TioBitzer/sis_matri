<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstitutionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $header = $request->header('X-Institution-Id');

        $institutionId = is_string($header)
            ? filter_var($header, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ])
            : false;

        if ($institutionId === false) {
            return response()->json([
                'message' => 'Debes indicar un ID de institución válido en X-Institution-Id.',
            ], 422);
        }

        $hasAccess = $user->institutions()
            ->whereKey($institutionId)
            ->wherePivot('is_active', true)
            ->where('institutions.is_active', true)
            ->exists();

        if (! $hasAccess) {
            return response()->json([
                'message' => 'No tienes acceso a esta institución.',
            ], 403);
        }

        $request->attributes->set('institution_id', $institutionId);

        return $next($request);
    }
}