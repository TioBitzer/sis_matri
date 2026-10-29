<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicUnitController extends Controller
{
    /** El contexto nunca se extrae del body: solo del middleware institution.access. */
    private function institutionId(Request $request): int
    {
        $institutionId = (int) $request->attributes->get('institution_id', 0);

        abort_if($institutionId < 1, 403, 'No se ha validado la institución seleccionada.');

        return $institutionId;
    }

    private function rules(int $institutionId, ?AcademicUnit $unit = null): array
    {
        $uniqueName = Rule::unique('academic_units', 'name')
            ->where('institution_id', $institutionId);

        if ($unit !== null) {
            $uniqueName->ignore($unit->id);
        }

        return [
            // Se conserva el campo requerido por el contrato previo de la API,
            // pero nunca puede diferir de X-Institution-Id validado.
            'institution_id' => ['required', 'integer', Rule::in([$institutionId])],
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'unit_type' => ['nullable', 'string', 'max:255'],
            'credits' => ['nullable', 'integer', 'min:0'],
            'hours' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'institution_id.required' => 'La institución es obligatoria.',
            'institution_id.integer' => 'La institución seleccionada no es válida.',
            'institution_id.in' => 'La institución no coincide con la institución seleccionada.',
            'name.required' => 'El nombre de la unidad académica es obligatorio.',
            'name.max' => 'El nombre de la unidad académica no puede superar los 255 caracteres.',
            'name.unique' => 'El nombre de la unidad académica ya está registrado en esta institución.',
            'code.string' => 'El código debe ser texto.',
            'code.max' => 'El código no puede superar los 50 caracteres.',
            'description.string' => 'La descripción debe ser texto.',
            'unit_type.string' => 'El tipo de unidad académica debe ser texto.',
            'unit_type.max' => 'El tipo de unidad académica no puede superar los 255 caracteres.',
            'credits.integer' => 'Los créditos deben ser un número entero.',
            'credits.min' => 'Los créditos no pueden ser negativos.',
            'hours.integer' => 'Las horas deben ser un número entero.',
            'hours.min' => 'Las horas no pueden ser negativas.',
            'is_active.boolean' => 'El estado debe ser válido.',
        ];
    }

    public function index(Request $request)
    {
        $institutionId = $this->institutionId($request);
        $units = AcademicUnit::with('institution')
            ->where('institution_id', $institutionId)
            ->get();

        return response()->json(['success' => true, 'data' => $units]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->institutionId($request);
        $validated = $request->validate($this->rules($institutionId), $this->messages());
        $validated['institution_id'] = $institutionId;

        $unit = AcademicUnit::create($validated);

        return response()->json(['success' => true, 'data' => $unit], 201);
    }

    public function show(Request $request, $id)
    {
        $unit = AcademicUnit::with('institution')
            ->where('institution_id', $this->institutionId($request))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $unit]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $unit = AcademicUnit::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate($this->rules($institutionId, $unit), $this->messages());
        $validated['institution_id'] = $institutionId;
        $unit->update($validated);

        return response()->json(['success' => true, 'data' => $unit]);
    }

    public function destroy(Request $request, $id)
    {
        $unit = AcademicUnit::where('institution_id', $this->institutionId($request))
            ->findOrFail($id);
        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unidad académica eliminada correctamente',
        ]);
    }
}
