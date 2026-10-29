<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InstitutionController extends Controller
{
    /** Contexto establecido unicamente por EnsureInstitutionAccess. */
    private function institutionId(Request $request): int
    {
        $id = (int) $request->attributes->get('institution_id', 0);
        abort_if($id < 1, 403, 'No se ha validado la institución seleccionada.');
        return $id;
    }

    /** Nunca basta con pertenecer a una institución para administrarla a nivel SaaS. */
    private function requirePlatformAdmin(Request $request): void
    {
        abort_unless($request->user() && $request->user()->is_platform_admin === true,
            403, 'Esta operación requiere administrador de plataforma.');
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId($request);
        return response()->json([
            'success' => true,
            'data' => Institution::query()->whereKey($institutionId)->get(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $institution = Institution::query()->whereKey($institutionId)->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $institution,
        ]);
    }

    public function store(Request $request)
    {
        $this->requirePlatformAdmin($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('institutions', 'code')],
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $institution = Institution::create($validated);
        return response()->json([
            'success' => true,
            'data' => $institution,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->requirePlatformAdmin($request);
        $institution = Institution::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('institutions', 'code')->ignore($institution->id)],
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        $institution->update($validated);
        return response()->json([
            'success' => true,
            'data' => $institution,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $this->requirePlatformAdmin($request);

        // BLOQUEO DE BORRADO TENANT V1:
        // No destruir por cascada ninguna institucion que tenga datos vinculados.
        // Se bloquea la fila mientras se comprueba y ejecuta la eliminacion.
        return DB::transaction(function () use ($id) {
            $institution = Institution::query()->lockForUpdate()->findOrFail($id);

            // Las tablas con institution_id incluyen, por ejemplo, sedes,
            // personas, periodos, programas, unidades y la tabla pivot
            // institution_user. Se inspecciona el esquema para cubrir tambien
            // nuevas tablas propias del tenant que se agreguen en el futuro.
            foreach (Schema::getTableListing() as $table) {
                if ($table === $institution->getTable() || !Schema::hasColumn($table, 'institution_id')) {
                    continue;
                }

                if (DB::table($table)->where('institution_id', $institution->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar la institución porque tiene datos asociados. Puede desactivarla para conservar su historial.',
                    ], 409);
                }
            }

            $institution->delete();

            return response()->json([
                'success' => true,
                'message' => 'Institución eliminada correctamente',
            ]);
        });
    }
}
