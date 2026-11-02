<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Historial de lectura exclusiva para el admin de la institucion seleccionada. */
final class InstitutionMembershipAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $institutionId = $request->attributes->get('institution_id');
        if (! is_int($institutionId) || $institutionId < 1) {
            abort(403, 'Falta contexto institucional validado.');
        }

        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = DB::table('institution_membership_audits')
            ->where('institution_id', $institutionId)
            ->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 30));

        $rows = collect($page->items())->map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'institution_id' => (int) $row->institution_id,
            'membership_id' => (int) $row->membership_id,
            'target_user_id' => (int) $row->target_user_id,
            'actor_user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            'actor_type' => $row->actor_type,
            'action' => $row->action,
            'old_role' => $row->old_role,
            'new_role' => $row->new_role,
            'old_is_active' => $row->old_is_active === null ? null : (bool) $row->old_is_active,
            'new_is_active' => (bool) $row->new_is_active,
            'old_person_id' => $row->old_person_id === null ? null : (int) $row->old_person_id,
            'new_person_id' => $row->new_person_id === null ? null : (int) $row->new_person_id,
            'created_at' => $row->created_at,
        ])->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
