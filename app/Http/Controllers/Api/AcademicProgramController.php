<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD de programas academicos limitado a la institucion autorizada.
 * Requiere middleware auth:sanctum e institution.access en las rutas.
 */
class AcademicProgramController extends Controller
{
    public function index(Request $request)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $programs = AcademicProgram::with('institution')
            ->where('institution_id', $institutionId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $programs,
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $validated = $request->validate(
            $this->rules($institutionId),
            $this->messages()
        );

        // No confiar en institution_id enviado por el cliente.
        $validated['institution_id'] = $institutionId;
        $program = AcademicProgram::create($validated);

        return response()->json([
            'success' => true,
            'data' => $program->load('institution'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $program = AcademicProgram::with('institution')
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $program,
        ]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $program = AcademicProgram::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $validated = $request->validate(
            $this->rules($institutionId, $program->id),
            $this->messages()
        );

        // Bloquear el traslado de un programa a otra institucion.
        $validated['institution_id'] = $institutionId;
        $program->update($validated);

        return response()->json([
            'success' => true,
            'data' => $program->fresh()->load('institution'),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $program = AcademicProgram::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $program->delete();

        return response()->json([
            'success' => true,
            'message' => 'Programa academico eliminado correctamente.',
        ]);
    }

    private function rules(int $institutionId, ?int $ignoreId = null): array
    {
        $nameUnique = Rule::unique('academic_programs', 'name')
            ->where('institution_id', $institutionId);

        if ($ignoreId !== null) {
            $nameUnique->ignore($ignoreId);
        }

        return [
            'institution_id' => [
                'required',
                'integer',
                'exists:institutions,id',
                Rule::in([$institutionId]),
            ],
            'name' => ['required', 'string', 'max:255', $nameUnique],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'institution_id.required' => 'La institucion es obligatoria.',
            'institution_id.exists' => 'La institucion seleccionada no existe.',
            'institution_id.in' => 'No tienes autorizacion para usar esta institucion.',
            'name.required' => 'El nombre del programa es obligatorio.',
            'name.unique' => 'El nombre del programa ya existe en esta institucion.',
            'code.required' => 'El codigo del programa es obligatorio.',
        ];
    }
}
