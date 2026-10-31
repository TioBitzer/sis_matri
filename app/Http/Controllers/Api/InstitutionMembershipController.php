<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestion de membres existentes en la institucion seleccionada.
 * Requiere los middleware auth:sanctum, institution.access e institution.role:institution_admin.
 */
class InstitutionMembershipController extends Controller
{
    private const ROLES = ['member', 'registrar', 'teacher', 'student', 'institution_admin'];

    public function index(Request $request): JsonResponse
    {
        $institutionId = $this->institutionId($request);

        $memberships = DB::table('institution_user as iu')
            ->join('users as u', 'u.id', '=', 'iu.user_id')
            ->where('iu.institution_id', $institutionId)
            ->orderBy('iu.id')
            ->get([
                'iu.id', 'iu.user_id', 'iu.institution_id', 'iu.person_id',
                'iu.role', 'iu.is_active', 'iu.created_at', 'iu.updated_at',
                'u.name', 'u.email',
            ])->map(fn ($row) => $this->serialize($row));

        return response()->json(['data' => $memberships]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $row = $this->getMembership($this->institutionId($request), $id);

        return response()->json(['data' => $this->serialize($row)]);
    }

    public function store(Request $request): JsonResponse
    {
        $institutionId = $this->institutionId($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['sometimes', 'required', Rule::in(self::ROLES)],
            'person_id' => ['sometimes', 'nullable', 'integer', Rule::exists('people', 'id')->where('institution_id', $institutionId)],
            'is_active' => ['sometimes', 'boolean'],
            'institution_id' => ['prohibited'],
            'is_platform_admin' => ['prohibited'],
        ]);

        $membershipId = DB::transaction(function () use ($request, $institutionId, $data): int {
            $this->lockAndRecheckAdmin($request, $institutionId);
            $exists = DB::table('institution_user')
                ->where('institution_id', $institutionId)
                ->where('user_id', $data['user_id'])
                ->exists();

            if ($exists) {
                abort(409, 'El usuario ya tiene una membresía en esta institución. Usa la actualización para reactivarla.');
            }

            $now = now();

            return DB::table('institution_user')->insertGetId([
                'institution_id' => $institutionId,
                'user_id' => $data['user_id'],
                'person_id' => $data['person_id'] ?? null,
                'role' => $data['role'] ?? 'member',
                'is_active' => $data['is_active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return response()->json([
            'data' => $this->serialize($this->getMembership($institutionId, $membershipId)),
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $institutionId = $this->institutionId($request);
        $data = $request->validate([
            'role' => ['sometimes', 'required', Rule::in(self::ROLES)],
            'person_id' => ['sometimes', 'nullable', 'integer', Rule::exists('people', 'id')->where('institution_id', $institutionId)],
            'is_active' => ['sometimes', 'boolean'],
            'user_id' => ['prohibited'],
            'institution_id' => ['prohibited'],
            'is_platform_admin' => ['prohibited'],
        ]);

        if ($data === []) {
            return response()->json(['message' => 'Debes enviar al menos un campo modificable.'], 422);
        }

        DB::transaction(function () use ($request, $institutionId, $id, $data): void {
            $this->lockAndRecheckAdmin($request, $institutionId);
            $current = DB::table('institution_user')
                ->where('id', $id)
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                abort(404);
            }

            $this->assertSafeChange($request, $institutionId, $current, $data);

            DB::table('institution_user')
                ->where('id', $id)
                ->where('institution_id', $institutionId)
                ->update(array_merge($data, ['updated_at' => now()]));
        }, 3);

        return response()->json([
            'data' => $this->serialize($this->getMembership($institutionId, $id)),
        ]);
    }

    /** La baja es lógica: se conserva la membresía para auditoría y reactivación. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $institutionId = $this->institutionId($request);

        DB::transaction(function () use ($request, $institutionId, $id): void {
            $this->lockAndRecheckAdmin($request, $institutionId);
            $current = DB::table('institution_user')
                ->where('id', $id)
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                abort(404);
            }

            $this->assertSafeChange($request, $institutionId, $current, ['is_active' => false]);

            DB::table('institution_user')
                ->where('id', $id)
                ->where('institution_id', $institutionId)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }, 3);

        return response()->json(['message' => 'Membresía desactivada correctamente.']);
    }

    private function institutionId(Request $request): int
    {
        $id = $request->attributes->get('institution_id');

        if (! is_int($id) || $id < 1) {
            abort(403, 'Falta el contexto institucional validado.');
        }

        return $id;
    }

    /** Serializa solo columnas autorizadas, nunca password, tokens o is_platform_admin. */
    private function serialize(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'institution_id' => (int) $row->institution_id,
            'user_id' => (int) $row->user_id,
            'person_id' => $row->person_id === null ? null : (int) $row->person_id,
            'name' => $row->name,
            'email' => $row->email,
            'role' => $row->role,
            'is_active' => (bool) $row->is_active,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    private function getMembership(int $institutionId, int $id): object
    {
        $row = DB::table('institution_user as iu')
            ->join('users as u', 'u.id', '=', 'iu.user_id')
            ->where('iu.id', $id)
            ->where('iu.institution_id', $institutionId)
            ->first([
                'iu.id', 'iu.user_id', 'iu.institution_id', 'iu.person_id',
                'iu.role', 'iu.is_active', 'iu.created_at', 'iu.updated_at',
                'u.name', 'u.email',
            ]);

        if ($row === null) {
            abort(404);
        }

        return $row;
    }

    /** Serializa modificaciones concurrentes y revalida la autoridad dentro de la transacción. */
    private function lockAndRecheckAdmin(Request $request, int $institutionId): void
    {
        $institution = DB::table('institutions')
            ->where('id', $institutionId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($institution === null) {
            abort(403, 'La institución está desactivada o no existe.');
        }

        $activeAdmin = DB::table('institution_user')
            ->where('institution_id', $institutionId)
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->where('role', 'institution_admin')
            ->exists();

        if (! $activeAdmin) {
            abort(403, 'Ya no tienes permiso para administrar membresías.');
        }
    }

    private function assertSafeChange(Request $request, int $institutionId, object $current, array $data): void
    {
        // Impide desactivarse, cambiarse el rol y alterar la propia membresía.
        if ((int) $current->user_id === (int) $request->user()->getAuthIdentifier()) {
            abort(403, 'No puedes modificar tu propia membresía.');
        }

        $isCurrentActiveAdmin = $current->role === 'institution_admin' && (bool) $current->is_active;
        $roleAfter = $data['role'] ?? $current->role;
        $activeAfter = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : (bool) $current->is_active;

        if ($isCurrentActiveAdmin && (! $activeAfter || $roleAfter !== 'institution_admin')) {
            $activeAdmins = DB::table('institution_user')
                ->where('institution_id', $institutionId)
                ->where('role', 'institution_admin')
                ->where('is_active', true)
                ->count();

            if ($activeAdmins <= 1) {
                abort(409, 'La institución debe conservar al menos un administrador activo.');
            }
        }
    }
}
