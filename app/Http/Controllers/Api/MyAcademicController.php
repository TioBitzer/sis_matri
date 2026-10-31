<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de solo lectura para el titular de la membresia seleccionada.
 * Requiere auth:sanctum, institution.access e institution.role:teacher/student.
 * Nunca acepta un identificador de docente/estudiante elegido por el cliente.
 */
class MyAcademicController extends Controller
{
    private function linkedPerson(Request $request, string $role): array
    {
        $institutionId = $request->attributes->get('institution_id');

        if (! is_int($institutionId) || $institutionId < 1 || ! $request->user()) {
            abort(403, 'Contexto institucional no autorizado.');
        }

        $personId = DB::table('institution_user as iu')
            ->join('people as p', 'p.id', '=', 'iu.person_id')
            ->where('iu.institution_id', $institutionId)
            ->where('iu.user_id', $request->user()->getAuthIdentifier())
            ->where('iu.is_active', true)
            ->where('iu.role', $role)
            ->where('p.institution_id', $institutionId)
            ->value('iu.person_id');

        if ($personId === null) {
            abort(403, 'Tu membresia no tiene una persona valida vinculada a esta institucion.');
        }

        return [$institutionId, (int) $personId];
    }

    private function teacherFor(Request $request): array
    {
        [$institutionId, $personId] = $this->linkedPerson($request, 'teacher');

        $teacher = DB::table('teachers as t')
            ->where('t.person_id', $personId)
            ->where('t.institution_id', $institutionId)
            ->first(['t.id', 't.person_id', 't.institution_id', 't.employee_code',
                't.specialization', 't.is_active']);

        if ($teacher === null) {
            abort(404, 'No existe una ficha docente vinculada a tu membresia.');
        }

        return [$institutionId, $teacher];
    }

    private function studentFor(Request $request): array
    {
        [$institutionId, $personId] = $this->linkedPerson($request, 'student');

        $student = DB::table('students as s')
            ->join('people as p', 'p.id', '=', 's.person_id')
            ->where('s.person_id', $personId)
            ->where('p.institution_id', $institutionId)
            ->first(['s.id', 's.person_id', 's.student_code', 's.admission_date', 's.status']);

        if ($student === null) {
            abort(404, 'No existe una ficha de estudiante vinculada a tu membresia.');
        }

        return [$institutionId, $student];
    }

    public function teacher(Request $request): JsonResponse
    {
        [, $teacher] = $this->teacherFor($request);

        return response()->json(['data' => $teacher]);
    }

    public function teachingAssignments(Request $request): JsonResponse
    {
        [$institutionId, $teacher] = $this->teacherFor($request);

        $page = DB::table('teaching_assignments as ta')
            ->join('academic_offerings as ao', 'ao.id', '=', 'ta.academic_offering_id')
            ->join('academic_units as au', 'au.id', '=', 'ao.academic_unit_id')
            ->join('academic_periods as ap', 'ap.id', '=', 'ao.academic_period_id')
            ->leftJoin('campuses as c', 'c.id', '=', 'ao.campus_id')
            ->where('ta.teacher_id', $teacher->id)
            ->where('au.institution_id', $institutionId)
            ->where('ap.institution_id', $institutionId)
            ->where(function ($query) use ($institutionId) {
                $query->whereNull('ao.campus_id')->orWhere('c.institution_id', $institutionId);
            })
            ->select([
                'ta.id', 'ta.academic_offering_id', 'ta.role', 'ao.section',
                'au.name as academic_unit_name', 'ap.name as academic_period_name',
                'c.name as campus_name',
            ])
            ->orderBy('ta.id')
            ->paginate(25);

        return response()->json($page);
    }

    public function student(Request $request): JsonResponse
    {
        [, $student] = $this->studentFor($request);

        return response()->json(['data' => $student]);
    }

    public function enrollments(Request $request): JsonResponse
    {
        [$institutionId, $student] = $this->studentFor($request);

        $page = DB::table('enrollments as e')
            ->join('academic_periods as ap', 'ap.id', '=', 'e.academic_period_id')
            ->join('academic_records as ar', 'ar.id', '=', 'e.academic_record_id')
            ->join('academic_programs as pr', 'pr.id', '=', 'ar.academic_program_id')
            ->join('academic_plans as pl', 'pl.id', '=', 'ar.academic_plan_id')
            ->where('e.student_id', $student->id)
            ->where('ar.student_id', $student->id)
            ->where('ap.institution_id', $institutionId)
            ->where('pr.institution_id', $institutionId)
            ->whereColumn('pl.academic_program_id', 'pr.id')
            ->select([
                'e.id', 'e.academic_period_id', 'e.academic_record_id',
                'e.enrollment_date', 'e.status', 'ap.name as academic_period_name',
                'pr.name as academic_program_name',
            ])
            ->orderByDesc('e.id')
            ->paginate(25);

        return response()->json($page);
    }

    public function academicRecords(Request $request): JsonResponse
    {
        [$institutionId, $student] = $this->studentFor($request);

        $page = DB::table('academic_records as ar')
            ->join('academic_programs as pr', 'pr.id', '=', 'ar.academic_program_id')
            ->join('academic_plans as pl', 'pl.id', '=', 'ar.academic_plan_id')
            ->where('ar.student_id', $student->id)
            ->where('pr.institution_id', $institutionId)
            ->whereColumn('pl.academic_program_id', 'pr.id')
            ->select([
                'ar.id', 'ar.academic_program_id', 'ar.academic_plan_id',
                'ar.start_date', 'ar.end_date', 'ar.status',
                'pr.name as academic_program_name', 'pl.name as academic_plan_name',
            ])
            ->orderByDesc('ar.id')
            ->paginate(25);

        return response()->json($page);
    }
}
