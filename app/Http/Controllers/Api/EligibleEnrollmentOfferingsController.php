<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCurriculumItem;
use App\Models\AcademicOffering;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ofertas compatibles con una matrícula. No reserva vacantes ni reemplaza las
 * validaciones transaccionales de EnrollmentDetailController.
 */
class EligibleEnrollmentOfferingsController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        $input = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $institutionId = (int) $request->attributes->get('institution_id');

        $enrollment = Enrollment::query()
            ->whereKey($id)
            ->whereHas('student.person', fn ($q) => $q->where('institution_id', $institutionId))
            ->whereHas('academicPeriod', fn ($q) => $q->where('institution_id', $institutionId))
            ->whereHas('academicRecord.academicProgram', fn ($q) => $q->where('institution_id', $institutionId))
            ->whereHas('academicRecord.academicPlan.academicProgram', fn ($q) => $q->where('institution_id', $institutionId))
            ->with('academicRecord')
            ->firstOrFail();

        $record = $enrollment->academicRecord;
        if (!$record ||
            (int) $record->student_id !== (int) $enrollment->student_id ||
            (int) $record->academicPlan?->academic_program_id !== (int) $record->academic_program_id
        ) {
            abort(404);
        }

        if ($enrollment->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se consultan ofertas para matrículas activas.',
            ], 409);
        }

        $alreadyRegisteredUnits = DB::table('enrollment_details')
            ->join('academic_offerings', 'academic_offerings.id', '=', 'enrollment_details.academic_offering_id')
            ->where('enrollment_details.enrollment_id', $enrollment->id)
            ->select('academic_offerings.academic_unit_id');

        $offers = AcademicOffering::query()
            ->where('academic_period_id', $enrollment->academic_period_id)
            ->where('is_active', true)
            ->whereHas('academicUnit', fn ($q) => $q->where('institution_id', $institutionId))
            ->whereHas('academicPeriod', fn ($q) => $q->where('institution_id', $institutionId))
            ->where(function ($q) use ($institutionId) {
                $q->whereNull('campus_id')
                    ->orWhereHas('campus', fn ($c) => $c->where('institution_id', $institutionId));
            })
            ->whereIn('academic_unit_id', AcademicCurriculumItem::query()
                ->where('academic_plan_id', $record->academic_plan_id)
                ->select('academic_unit_id'))
            ->whereNotIn('academic_unit_id', $alreadyRegisteredUnits)
            ->with(['academicUnit:id,name,code', 'campus:id,name,code'])
            ->withCount(['enrollmentDetails as occupied' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('academic_unit_id')
            ->orderBy('section')
            ->orderBy('id')
            ->paginate((int) ($input['per_page'] ?? 20));

        $rows = collect($offers->items())->map(static function (AcademicOffering $offering): array {
            $capacity = $offering->capacity === null ? null : (int) $offering->capacity;
            $occupied = (int) $offering->occupied;
            $remaining = $capacity === null ? null : max(0, $capacity - $occupied);

            return [
                'id' => $offering->id,
                'academic_unit' => $offering->academicUnit ? [
                    'id' => $offering->academicUnit->id,
                    'name' => $offering->academicUnit->name,
                    'code' => $offering->academicUnit->code,
                ] : null,
                'campus' => $offering->campus ? [
                    'id' => $offering->campus->id,
                    'name' => $offering->campus->name,
                    'code' => $offering->campus->code,
                ] : null,
                'section' => $offering->section,
                'capacity' => $capacity,
                'occupied' => $occupied,
                'remaining' => $remaining,
                'can_enroll' => $remaining === null || $remaining > 0,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'meta' => [
                'current_page' => $offers->currentPage(),
                'last_page' => $offers->lastPage(),
                'per_page' => $offers->perPage(),
                'total' => $offers->total(),
            ],
        ]);
    }
}
