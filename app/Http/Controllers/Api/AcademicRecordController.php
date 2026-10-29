<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use App\Models\AcademicRecord;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicRecordController extends Controller
{
    private const RELATIONS = [
        'student.person',
        'academicProgram',
        'academicPlan',
    ];

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->recordsForInstitution($request)->with(self::RELATIONS)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), $this->messages());

        $record = AcademicRecord::create($validated);

        return response()->json([
            'success' => true,
            'data' => $record->load(self::RELATIONS),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $record = $this->recordsForInstitution($request)->with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function update(Request $request, $id)
    {
        $record = $this->recordsForInstitution($request)->findOrFail($id);
        $validated = $request->validate($this->rules($request), $this->messages());

        // Los datos de identidad académica deben mantenerse cuando existen matrículas.
        // Solo se pueden actualizar datos como estado y fechas en ese caso.
        if ($record->enrollments()->exists() && (
            (int) $record->student_id !== (int) $validated['student_id'] ||
            (int) $record->academic_program_id !== (int) $validated['academic_program_id'] ||
            (int) $record->academic_plan_id !== (int) $validated['academic_plan_id']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el estudiante, programa o plan de un historial con matrículas asociadas.',
            ], 409);
        }

        $record->update($validated);

        return response()->json([
            'success' => true,
            'data' => $record->fresh()->load(self::RELATIONS),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        $record = $this->recordsForInstitution($request)->findOrFail($id);

        if ($record->enrollments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el historial académico porque tiene matrículas asociadas.',
            ], 409);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro académico eliminado correctamente',
        ], 200);
    }

    /** Institution selected and authorized by the institution.access middleware. */
    private function institutionId(Request $request): int
    {
        $institutionId = (int) $request->attributes->get('institution_id', 0);
        abort_if($institutionId < 1, 403, 'La institución seleccionada no fue validada.');

        return $institutionId;
    }

    /** Filter every read/write lookup by student, academic program and plan institution. */
    private function recordsForInstitution(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $institutionId = $this->institutionId($request);

        return AcademicRecord::query()
            ->whereHas('student.person', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereHas('academicProgram', fn ($query) => $query->where('institution_id', $institutionId))
            ->whereHas('academicPlan.academicProgram', fn ($query) => $query->where('institution_id', $institutionId));
    }

    private function rules(Request $request): array
    {
        $institutionId = $this->institutionId($request);

        return [
            'student_id' => [
                'required', 'integer', 'exists:students,id',
                function ($attribute, $value, $fail) use ($request, $institutionId) {
                    $student = Student::with('person')->find($value);
                    $program = AcademicProgram::find($request->input('academic_program_id'));

                    if ($student && (!$student->person ||
                        (int) $student->person->institution_id !== $institutionId)) {
                        $fail('El estudiante no pertenece a la institución seleccionada.');
                    }

                    // Conservar la validación original de coherencia entre estudiante y programa.
                    if ($student && $program && (
                        !$student->person ||
                        (int) $student->person->institution_id !== (int) $program->institution_id
                    )) {
                        $fail('El estudiante y el programa deben pertenecer a la misma institución.');
                    }
                },
            ],
            'academic_program_id' => [
                'required', 'integer',
                Rule::exists('academic_programs', 'id')->where('institution_id', $institutionId),
            ],
            'academic_plan_id' => [
                'required', 'integer', 'exists:academic_plans,id',
                function ($attribute, $value, $fail) use ($request, $institutionId) {
                    $plan = AcademicPlan::with('academicProgram')->find($value);

                    if ($plan && (!$plan->academicProgram ||
                        (int) $plan->academicProgram->institution_id !== $institutionId)) {
                        $fail('El plan académico no pertenece a la institución seleccionada.');
                    }

                    if ($plan && (int) $plan->academic_program_id !== (int) $request->input('academic_program_id')) {
                        $fail('El plan académico no pertenece al programa seleccionado.');
                    }
                },
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            // Estados de referencia: ajustar esta lista si el negocio define otros.
            'status' => [
                'required', 'string', 'max:50',
                Rule::in(['active', 'inactive', 'suspended', 'completed', 'withdrawn', 'graduated', 'transferred']),
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'student_id.required' => 'El estudiante es obligatorio.',
            'student_id.exists' => 'El estudiante seleccionado no existe.',
            'academic_program_id.required' => 'El programa académico es obligatorio.',
            'academic_program_id.exists' => 'El programa académico seleccionado no existe.',
            'academic_plan_id.required' => 'El plan académico es obligatorio.',
            'academic_plan_id.exists' => 'El plan académico seleccionado no existe.',
            'start_date.required' => 'La fecha de inicio es obligatoria.',
            'start_date.date' => 'La fecha de inicio no es válida.',
            'end_date.date' => 'La fecha de finalización no es válida.',
            'end_date.after_or_equal' => 'La fecha de finalización debe ser igual o posterior a la fecha de inicio.',
            'status.required' => 'El estado del historial académico es obligatorio.',
            'status.max' => 'El estado no puede superar los 50 caracteres.',
            'status.in' => 'El estado del historial académico no es válido.',
        ];
    }
}
