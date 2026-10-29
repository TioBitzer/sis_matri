<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicPeriodController extends Controller
{
    /** Solo usamos el ID validado por institution.access, nunca el body. */
    private function institutionId(Request $request): int
    {
        $id = (int) $request->attributes->get('institution_id', 0);
        abort_if($id < 1, 403, 'No se ha validado la institución seleccionada.');
        return $id;
    }

    private function rules(int $institutionId, ?AcademicPeriod $period = null): array
    {
        $uniqueName = Rule::unique('academic_periods', 'name')
            ->where('institution_id', $institutionId);

        if ($period !== null) {
            $uniqueName->ignore($period->id);
        }

        return [
            // Conservamos el contrato existente (campo obligatorio), sin permitir suplantación.
            'institution_id' => ['required', 'integer', Rule::in([$institutionId])],
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'code' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'institution_id.required' => 'La institución es obligatoria.',
            'institution_id.integer' => 'La institución seleccionada no es válida.',
            'institution_id.in' => 'La institución no coincide con la institución seleccionada.',
            'name.required' => 'El nombre del período académico es obligatorio.',
            'name.max' => 'El nombre del período académico no puede superar los 255 caracteres.',
            'name.unique' => 'El nombre del período académico ya está registrado en esta institución.',
            'code.string' => 'El código debe ser un texto válido.',
            'code.max' => 'El código no puede superar los 50 caracteres.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.required' => 'La fecha de fin es obligatoria.',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'is_active.boolean' => 'El estado debe ser válido.',
        ];
    }

    public function index(Request $request)
    {
        $periods = AcademicPeriod::with('institution')
            ->where('institution_id', $this->institutionId($request))
            ->get();

        return response()->json(['success' => true, 'data' => $periods]);
    }

    public function store(Request $request)
    {
        $institutionId = $this->institutionId($request);
        $validated = $request->validate($this->rules($institutionId), $this->messages());
        $validated['institution_id'] = $institutionId;

        $period = AcademicPeriod::create($validated);

        return response()->json(['success' => true, 'data' => $period], 201);
    }

    public function show(Request $request, $id)
    {
        $period = AcademicPeriod::with('institution')
            ->where('institution_id', $this->institutionId($request))
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $period]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $period = AcademicPeriod::where('institution_id', $institutionId)->findOrFail($id);
        $validated = $request->validate($this->rules($institutionId, $period), $this->messages());
        $validated['institution_id'] = $institutionId;

        $period->update($validated);

        return response()->json(['success' => true, 'data' => $period]);
    }

    public function destroy(Request $request, $id)
    {
        $period = AcademicPeriod::where('institution_id', $this->institutionId($request))
            ->findOrFail($id);
        $period->delete();

        return response()->json([
            'success' => true,
            'message' => 'Período académico eliminado correctamente',
        ]);
    }
}