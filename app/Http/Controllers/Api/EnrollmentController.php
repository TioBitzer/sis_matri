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

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->tenantQuery($this->selectedInstitutionId($request))
                ->with(self::RELATIONS)
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->validateTenantReferences($validated, $this->selectedInstitutionId($request));
        $this->validateCoherence($validated);

        $enrollment = Enrollment::create($validated);

        return response()->json([
            'success' => true,
            'data' => $enrollment->load(self::RELATIONS),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $enrollment = $this->tenantQuery($this->selectedInstitutionId($request))
            ->with(self::RELATIONS)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $enrollment = $this->tenantQuery($this->selectedInstitutionId($request))
            ->findOrFail($id);
        $validated = $request->validate($this->rules());
        $this->validateTenantReferences($validated, $this->selectedInstitutionId($request));

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

    public function destroy(Request $request, $id)
    {
        $enrollment = $this->tenantQuery($this->selectedInstitutionId($request))
            ->findOrFail($id);

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

    /** La institución seleccionada se determina solo por el middleware de acceso. */
    private function selectedInstitutionId(Request $request): int
    {
        return (int) $request->attributes->get('institution_id');
    }

    /**
     * Nunca consultar una matrícula solo por ID: debe pertenecer por todas sus
     * relaciones principales a la institución autorizada en la solicitud.
     */
    private function tenantQuery(int $institutionId)
    {
        return Enrollment::query()
            ->whereHas('student.person', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->whereHas('academicPeriod', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->whereHas('academicRecord.academicProgram', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            });
    }

    /**
     * Una combinación coherente entre sí no basta: todas las referencias deben
     * pertenecer a la institución elegida en X-Institution-Id.
     */
    private function validateTenantReferences(array $data, int $institutionId): void
    {
        $errors = [];

        $studentBelongs = Student::query()
            ->whereKey($data['student_id'])
            ->whereHas('person', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })->exists();

        if (!$studentBelongs) {
            $errors['student_id'] = 'El estudiante no pertenece a la institución seleccionada.';
        }

        $periodBelongs = AcademicPeriod::query()
            ->whereKey($data['academic_period_id'])
            ->where('institution_id', $institutionId)
            ->exists();

        if (!$periodBelongs) {
            $errors['academic_period_id'] = 'El período no pertenece a la institución seleccionada.';
        }

        $recordBelongs = AcademicRecord::query()
            ->whereKey($data['academic_record_id'])
            ->whereHas('student.person', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->whereHas('academicProgram', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->whereHas('academicPlan.academicProgram', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->exists();

        if (!$recordBelongs) {
            $errors['academic_record_id'] = 'El historial no pertenece a la institución seleccionada.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
