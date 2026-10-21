<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentController extends Controller
{
    private const RELATIONS = [
        'student.person',
        'academicPeriod',
        'academicRecord.academicProgram',
        'academicRecord.academicPlan',
    ];

    // Estados de referencia del proceso de matrícula; adaptar si el negocio define otros.
    private const STATUSES = [
        'active', 'pending', 'cancelled', 'completed', 'suspended', 'withdrawn', 'inactive',
    ];

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Enrollment::with(self::RELATIONS)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->validateCoherence($validated);

        $enrollment = Enrollment::create($validated);

        return response()->json([
            'success' => true,
            'data' => $enrollment->load(self::RELATIONS),
        ], 201);
    }

    public function show($id)
    {
        $enrollment = Enrollment::with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $enrollment = Enrollment::findOrFail($id);
        $validated = $request->validate($this->rules());

        // No se deben mover cursos inscritos a otro estudiante, expediente o período.
        if ($enrollment->enrollmentDetails()->exists() && (
            (int) $enrollment->student_id !== (int) $validated['student_id'] ||
            (int) $enrollment->academic_period_id !== (int) $validated['academic_period_id'] ||
            (int) $enrollment->academic_record_id !== (int) $validated['academic_record_id']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar el estudiante, período o historial académico de una matrícula con cursos registrados.',
            ], 409);
        }

        $this->validateCoherence($validated, $enrollment);
        $enrollment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $enrollment->fresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy($id)
    {
        $enrollment = Enrollment::findOrFail($id);

        // enrollment_details tiene cascadeOnDelete: impedir pérdida accidental de cursos.
        if ($enrollment->enrollmentDetails()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una matrícula que tiene cursos registrados.',
            ], 409);
        }

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matrícula eliminada correctamente',
        ]);
    }

    private function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'academic_period_id' => ['required', 'integer', 'exists:academic_periods,id'],
            'academic_record_id' => ['required', 'integer', 'exists:academic_records,id'],
            'enrollment_date' => ['required', 'date'],
            'status' => ['required', 'string', 'max:50', Rule::in(self::STATUSES)],
        ];
    }

    /**
     * Evita registros entre instituciones distintas y duplicados antes de tocar la BD.
     * Las restricciones UNIQUE de las migraciones permanecen como última protección.
     */
    private function validateCoherence(array $data, ?Enrollment $current = null): void
    {
        $student = Student::with('person')->find($data['student_id']);
        $period = AcademicPeriod::find($data['academic_period_id']);
        $record = AcademicRecord::with(['academicProgram', 'academicPlan'])->find($data['academic_record_id']);

        if (!$student || !$period || !$record) {
            throw ValidationException::withMessages([
                'student_id' => 'Alguna referencia de la matrícula ya no existe. Vuelve a intentarlo.',
            ]);
        }

        if ((int) $record->student_id !== (int) $student->id) {
            throw ValidationException::withMessages([
                'academic_record_id' => 'El historial académico no pertenece al estudiante seleccionado.',
            ]);
        }

        if (!$student->person || !$record->academicProgram ||
            (int) $student->person->institution_id !== (int) $record->academicProgram->institution_id) {
            throw ValidationException::withMessages([
                'academic_record_id' => 'El historial académico y el estudiante deben pertenecer a la misma institución.',
            ]);
        }

        if (!$record->academicPlan ||
            (int) $record->academicPlan->academic_program_id !== (int) $record->academic_program_id) {
            throw ValidationException::withMessages([
                'academic_record_id' => 'El plan del historial académico no pertenece a su programa.',
            ]);
        }

        if ((int) $period->institution_id !== (int) $student->person->institution_id) {
            throw ValidationException::withMessages([
                'academic_period_id' => 'El período académico y el estudiante deben pertenecer a la misma institución.',
            ]);
        }

        // Restricción real de BD: student_period_unique.
        $byStudentAndPeriod = Enrollment::where('student_id', $data['student_id'])
            ->where('academic_period_id', $data['academic_period_id']);

        if ($current) {
            $byStudentAndPeriod->where('id', '!=', $current->id);
        }

        if ($byStudentAndPeriod->exists()) {
            throw ValidationException::withMessages([
                'academic_period_id' => 'El estudiante ya tiene una matrícula registrada en este período.',
            ]);
        }

        // Existe también un índice único por historial + período en este proyecto.
        $byRecordAndPeriod = Enrollment::where('academic_record_id', $data['academic_record_id'])
            ->where('academic_period_id', $data['academic_period_id']);

        if ($current) {
            $byRecordAndPeriod->where('id', '!=', $current->id);
        }

        if ($byRecordAndPeriod->exists()) {
            throw ValidationException::withMessages([
                'academic_period_id' => 'Este historial académico ya tiene una matrícula en el período seleccionado.',
            ]);
        }
    }
}
