<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MyInstitutionsController extends Controller
{
    /**
     * Devuelve exclusivamente las instituciones activas en las que el usuario
     * autenticado mantiene una membresía activa. No requiere contexto tenant:
     * este endpoint permite elegirlo antes de enviar X-Institution-Id.
     */
    public function index(Request $request): JsonResponse
    {
        $institutions = DB::table('institution_user as membership')
            ->join('institutions as institution', 'institution.id', '=', 'membership.institution_id')
            ->where('membership.user_id', $request->user()->getAuthIdentifier())
            ->where('membership.is_active', true)
            ->where('institution.is_active', true)
            ->orderBy('institution.name')
            ->orderBy('institution.id')
            ->get([
                'institution.id',
                'institution.name',
                'institution.code',
                'institution.type',
                'membership.role',
            ])
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'code' => (string) $row->code,
                'type' => (string) $row->type,
                'role' => (string) $row->role,
            ])
            ->all();

        return response()->json(['data' => $institutions]);
    }
}
