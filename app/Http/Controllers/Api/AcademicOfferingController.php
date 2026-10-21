<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicOffering;
use App\Models\AcademicPeriod;
use App\Models\AcademicUnit;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicOfferingController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => AcademicOffering::with(['academicUnit', 'academicPeriod', 'campus'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), $this->messages());
        $validated['is_active'] = $validated['is_active'] ?? true;

        $offering = AcademicOffering::create($validated);

        return response()->json([
            'success' => true,
            'data' => $offering->load(['academicUnit', 'academicPeriod', 'campus']),
        ], 201);
    }

    public function show($id)
    {
        $offering = AcademicOffering::with(['academicUnit', 'academicPeriod', 'campus'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $offering,
        ]);
    }

    public function update(Request $request, $id)
    {
        $offering = AcademicOffering::findOrFail($id);
        $validated = $request->validate($this->rules($request, $offering), $this->messages());

        // Nunca reducir la capacidad por debajo de los detalles de matrícula activos.
        $occupied = $offering->enrollmentDetails()->where('status', 'active')->count();
        if ($validated['capacity'] < $occupied) {
            return response()->json([
                'success' => false,
                'message' => 'La capacidad no puede ser menor que los estudiantes matriculados activos.',
                'occupied' => $occupied,
            ], 409);
        }

        // Con historial de matrícula, el curso y el periodo deben mantenerse estables.
        if ($offering->enrollmentDetails()->exists() && (
            (int) $validated['academic_unit_id'] !== (int) $offering->academic_unit_id ||
            (int) $validated['academic_period_id'] !== (int) $offering->academic_period_id
        )) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el curso o periodo de una oferta con matrículas asociadas.',
            ], 409);
        }

        $offering->update($validated);

        return response()->json([
            'success' => true,
            'data' => $offering->fresh()->load(['academicUnit', 'academicPeriod', 'campus']),
        ]);
    }

    public function destroy($id)
    {
        $offering = AcademicOffering::findOrFail($id);

        if ($offering->enrollmentDetails()->exists() || $offering->teachingAssignments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una oferta académica con matrículas o asignaciones docentes asociadas.',
            ], 409);
        }

        $offering->delete();

        return response()->json([
            'success' => true,
            'message' => 'Oferta académica eliminada correctamente',
        ], 200);
    }

    private function rules(Request $request, ?AcademicOffering $offering = null): array
    {
        return [
            'academic_unit_id' => [
                'required', 'integer', 'exists:academic_units,id',
            ],
            'academic_period_id' => [
                'required', 'integer', 'exists:academic_periods,id',
                function ($attribute, $value, $fail) use ($request) {
                    $unit = AcademicUnit::find($request->input('academic_unit_id'));
                    $period = AcademicPeriod::find($value);
                    if ($unit && $period && (int) $unit->institution_id !== (int) $period->institution_id) {
                        $fail('El curso y el periodo académico deben pertenecer a la misma institución.');
                    }
                },
            ],
            'campus_id' => [
                'required', 'integer', 'exists:campuses,id',
                function ($attribute, $value, $fail) use ($request) {
                    $unit = AcademicUnit::find($request->input('academic_unit_id'));
                    $campus = Campus::find($value);
                    if ($unit && $campus && (int) $unit->institution_id !== (int) $campus->institution_id) {
                        $fail('La sede y el curso deben pertenecer a la misma institución.');
                    }
                },
            ],
            'section' => [
                'required', 'string', 'max:50',
                Rule::unique('academic_offerings', 'section')
                    ->where('academic_unit_id', $request->input('academic_unit_id'))
                    ->where('academic_period_id', $request->input('academic_period_id'))
                    ->ignore($offering?->id),
            ],
            'capacity' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'academic_unit_id.required' => 'El curso es obligatorio.',
            'academic_unit_id.exists' => 'El curso seleccionado no existe.',
            'academic_period_id.required' => 'El periodo académico es obligatorio.',
            'academic_period_id.exists' => 'El periodo académico seleccionado no existe.',
            'campus_id.required' => 'La sede es obligatoria.',
            'campus_id.exists' => 'La sede seleccionada no existe.',
            'section.required' => 'La sección es obligatoria.',
            'section.max' => 'La sección no puede superar los 50 caracteres.',
            'section.unique' => 'La sección ya existe para este curso y periodo.',
            'capacity.required' => 'La capacidad es obligatoria.',
            'capacity.integer' => 'La capacidad debe ser un número entero.',
            'capacity.min' => 'La capacidad debe ser al menos uno.',
            'is_active.boolean' => 'El estado debe ser válido.',
        ];
    }
}
