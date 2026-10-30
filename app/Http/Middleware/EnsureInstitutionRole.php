<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza operaciones segun el rol de la membresia en la institucion validada.
 * Requiere: auth:sanctum, institution.access, institution.role:rol1,rol2.
 * Nunca confia en role o institution_id recibidos en el cuerpo de la solicitud.
 */
class EnsureInstitutionRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        if ($request->user() === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Solo institution.access establece este atributo tras validar la membresia.
        $institutionId = $request->attributes->get('institution_id');

        if (! is_int($institutionId) || $institutionId < 1 || $allowedRoles === []) {
            return response()->json([
                'message' => 'Contexto institucional o permisos no configurados.',
            ], 403);
        }

        // Consultar el rol vigente en BD; nunca confiar en cabeceras o datos enviados.
        // Revalidar la membresia y la institucion, incluso si otra capa fallo.
        $institution = $request->user()->institutions()
            ->whereKey($institutionId)
            ->wherePivot('is_active', true)
            ->where('institutions.is_active', true)
            ->first();

        $role = $institution?->pivot?->role;

        if (! is_string($role) || ! in_array($role, $allowedRoles, true)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta operación.',
            ], 403);
        }

        $request->attributes->set('institution_role', $role);

        return $next($request);
    }
}
