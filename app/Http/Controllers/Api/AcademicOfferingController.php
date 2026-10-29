<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicOffering;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicOfferingController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->offeringsForInstitution($request)
                ->with(['academicUnit', 'academicPeriod', 'campus'])->get(),
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

    public function show(Request $request, $id)
    {
        $offering = $this->offeringsForInstitution($request)
            ->with(['academicUnit', 'academicPeriod', 'campus'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $offering,
        ]);
    }

    public function update(Request $request, $id)
    {
        $offering = $this->offeringsForInstitution($request)->findOrFail($id);
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

    public function destroy(Request $request, $id)
    {
        $offering = $this->offeringsForInstitution($request)->findOrFail($id);

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

    /**
     * Todas las referencias de una oferta deben pertenecer a la institucion
     * autorizada en EnsureInstitutionAccess (nunca confiar en el body).
     */
    private function rules(Request $request, ?AcademicOffering $offering = null): array
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        return [
            'academic_unit_id' => [
                'required', 'integer',
                Rule::exists('academic_units', 'id')->where('institution_id', $institutionId),
            ],
            'academic_period_id' => [
                'required', 'integer',
                Rule::exists('academic_periods', 'id')->where('institution_id', $institutionId),
            ],
            'campus_id' => [
                'required', 'integer',
                Rule::exists('campuses', 'id')->where('institution_id', $institutionId),
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

    /**
     * Una oferta solo es visible cuando su curso, periodo y sede pertenecen
     * a la institucion seleccionada por el middleware.
     */
    private function offeringsForInstitution(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        return AcademicOffering::query()
            ->whereHas('academicUnit', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereHas('academicPeriod', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereHas('campus', fn ($query) => $query->where('institution_id', $institutionId));
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
