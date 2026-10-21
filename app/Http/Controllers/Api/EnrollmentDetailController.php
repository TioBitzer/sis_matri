<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCurriculumItem;
use App\Models\AcademicOffering;
use App\Models\Enrollment;
use App\Models\EnrollmentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentDetailController extends Controller
{
    private const RELATIONS = [
        'enrollment.student.person',
        'enrollment.academicPeriod',
        'enrollment.academicRecord.academicProgram',
        'enrollment.academicRecord.academicPlan',
        'academicOffering.academicUnit',
        'academicOffering.academicPeriod',
        'academicOffering.campus',
    ];

    private const STATUSES = ['active', 'cancelled', 'completed'];

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => EnrollmentDetail::with(self::RELATIONS)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return DB::transaction(function () use ($data) {
            // Serializa los cambios de una misma matrícula antes de reservar vacantes.
            $enrollment = Enrollment::whereKey($data['enrollment_id'])
                ->lockForUpdate()->firstOrFail();
            $offering = AcademicOffering::whereKey($data['academic_offering_id'])
                ->lockForUpdate()->firstOrFail();

            $this->validateEnrollmentOffering($enrollment, $offering);
            $this->validateDuplicate($enrollment, $offering);

            if (!$offering->is_active) {
                $this->invalid('academic_offering_id', 'No se pueden registrar cursos en una oferta académica inactiva.');
            }

            $this->validateVacancy($enrollment, $offering, $data['status'], true);

            $detail = EnrollmentDetail::create($data);

            return response()->json([
                'success' => true,
                'data' => $detail->load(self::RELATIONS),
            ], 201);
        }, 3);
    }

    public function show($id)
    {
        $detail = EnrollmentDetail::with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate($this->rules());

        return DB::transaction(function () use ($id, $data) {
            $snapshot = EnrollmentDetail::findOrFail($id);

            // Se bloquean las matrículas en un orden estable para evitar carreras.
            $enrollments = Enrollment::whereIn('id', [
                $snapshot->enrollment_id,
                $data['enrollment_id'],
            ])->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $enrollment = $enrollments->get($data['enrollment_id']);
            if (!$enrollment) {
                abort(404, 'Matrícula no encontrada.');
            }

            // Bloqueos de las dos secciones, siempre por ID ascendente.
            $offerings = AcademicOffering::whereIn('id', [
                $snapshot->academic_offering_id,
                $data['academic_offering_id'],
            ])->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $offering = $offerings->get($data['academic_offering_id']);
            if (!$offering) {
                abort(404, 'Oferta académica no encontrada.');
            }

            $detail = EnrollmentDetail::whereKey($id)->lockForUpdate()->firstOrFail();
            if (
                (int) $detail->enrollment_id !== (int) $snapshot->enrollment_id ||
                (int) $detail->academic_offering_id !== (int) $snapshot->academic_offering_id
            ) {
                return $this->changedResponse();
            }

            $this->validateEnrollmentOffering($enrollment, $offering);
            $this->validateDuplicate($enrollment, $offering, $detail);

            $changingEnrollment = (int) $detail->enrollment_id !== (int) $data['enrollment_id'];
            $changingOffering = (int) $detail->academic_offering_id !== (int) $data['academic_offering_id'];
            $changingLinks = $changingEnrollment || $changingOffering;

            // Cambiar de estudiante conservando la misma sección no consume
            // una plaza adicional: el detalle sigue ocupando una sola vacante.
            $needsVacancy = $data['status'] === 'active'
                && ($detail->status !== 'active' || $changingOffering);

            if ($data['status'] === 'active' && $changingEnrollment && $enrollment->status !== 'active') {
                $this->invalid('enrollment_id', 'Solo se pueden activar cursos de una matrícula activa.');
            }

            if ($changingLinks && !$offering->is_active) {
                $this->invalid('academic_offering_id', 'No se puede trasladar una inscripción a una oferta inactiva.');
            }

            $this->validateVacancy($enrollment, $offering, $data['status'], $needsVacancy);

            $detail->update($data);

            return response()->json([
                'success' => true,
                'data' => $detail->fresh()->load(self::RELATIONS),
            ]);
        }, 3);
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $snapshot = EnrollmentDetail::findOrFail($id);

            // El mismo orden de bloqueo utilizado por store y update.
            Enrollment::whereKey($snapshot->enrollment_id)
                ->lockForUpdate()->firstOrFail();
            AcademicOffering::whereKey($snapshot->academic_offering_id)
                ->lockForUpdate()->firstOrFail();

            $detail = EnrollmentDetail::whereKey($id)->lockForUpdate()->firstOrFail();
            if (
                (int) $detail->enrollment_id !== (int) $snapshot->enrollment_id ||
                (int) $detail->academic_offering_id !== (int) $snapshot->academic_offering_id
            ) {
                return $this->changedResponse();
            }

            $detail->delete();

            return response()->json([
                'success' => true,
                'message' => 'Detalle de matrícula eliminado correctamente',
            ]);
        }, 3);
    }

    private function rules(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'academic_offering_id' => ['required', 'integer', 'exists:academic_offerings,id'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ];
    }

    private function validateEnrollmentOffering(Enrollment $enrollment, AcademicOffering $offering): void
    {
        $enrollment->loadMissing([
            'student.person',
            'academicPeriod',
            'academicRecord.academicProgram',
            'academicRecord.academicPlan',
        ]);
        $offering->loadMissing(['academicUnit', 'academicPeriod', 'campus']);

        $record = $enrollment->academicRecord;
        $institutionId = $enrollment->student?->person?->institution_id;

        if (!$institutionId || !$record ||
            (int) $record->student_id !== (int) $enrollment->student_id ||
            (int) $record->academicProgram?->institution_id !== (int) $institutionId ||
            (int) $record->academicPlan?->academic_program_id !== (int) $record->academic_program_id ||
            (int) $enrollment->academicPeriod?->institution_id !== (int) $institutionId) {
            $this->invalid('enrollment_id', 'La matrícula, el estudiante y su historial deben pertenecer a la misma institución y programa.');
        }

        if ((int) $enrollment->academic_period_id !== (int) $offering->academic_period_id) {
            $this->invalid('academic_offering_id', 'La oferta académica no pertenece al período de la matrícula.');
        }

        if (
            (int) $offering->academicUnit?->institution_id !== (int) $institutionId ||
            (int) $offering->academicPeriod?->institution_id !== (int) $institutionId ||
            ($offering->campus_id !== null &&
                (int) $offering->campus?->institution_id !== (int) $institutionId)
        ) {
            $this->invalid('academic_offering_id', 'La oferta, su curso, período y sede deben pertenecer a la institución del estudiante.');
        }

        if (!AcademicCurriculumItem::where('academic_plan_id', $record->academic_plan_id)
            ->where('academic_unit_id', $offering->academic_unit_id)->exists()) {
            $this->invalid('academic_offering_id', 'La asignatura no pertenece al plan académico del estudiante.');
        }
    }

    private function validateDuplicate(
        Enrollment $enrollment,
        AcademicOffering $offering,
        ?EnrollmentDetail $current = null
    ): void {
        $details = EnrollmentDetail::where('enrollment_id', $enrollment->id);
        if ($current !== null) {
            $details->where('id', '!=', $current->id);
        }

        if ((clone $details)->where('academic_offering_id', $offering->id)->exists()) {
            $this->invalid('academic_offering_id', 'Esta oferta ya está registrada en la matrícula.');
        }

        // También impide duplicar la misma asignatura mediante otra sección.
        if ($details->whereHas('academicOffering', function ($query) use ($offering) {
            $query->where('academic_unit_id', $offering->academic_unit_id);
        })->exists()) {
            $this->invalid('academic_offering_id', 'El estudiante ya tiene esta asignatura registrada en otra sección.');
        }
    }

    private function validateVacancy(
        Enrollment $enrollment,
        AcademicOffering $offering,
        string $status,
        bool $needsVacancy
    ): void {
        if (!$needsVacancy || $status !== 'active') {
            return;
        }

        if ($enrollment->status !== 'active') {
            $this->invalid('enrollment_id', 'Solo se pueden activar cursos de una matrícula activa.');
        }
        if (!$offering->is_active) {
            $this->invalid('academic_offering_id', 'La oferta académica no está activa.');
        }

        // NULL representa una oferta sin límite explícito en el esquema actual.
        if ($offering->capacity === null) {
            return;
        }

        $occupied = EnrollmentDetail::where('academic_offering_id', $offering->id)
            ->where('status', 'active')->count();

        if ($occupied >= (int) $offering->capacity) {
            $this->invalid('academic_offering_id', 'No hay vacantes disponibles en esta sección.');
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function changedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'La inscripción fue modificada por otra operación. Intenta nuevamente.',
        ], 409);
    }
}
