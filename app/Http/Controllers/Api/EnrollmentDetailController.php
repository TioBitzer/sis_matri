<?php

namespace App\Http\Controllers\Api;
use App\Models\AcademicCurriculumItem;
use App\Http\Controllers\Controller;
use App\Models\EnrollmentDetail;
use Illuminate\Http\Request;
use App\Models\Enrollment;
use App\Models\AcademicOffering;
use Illuminate\Support\Facades\DB;



class EnrollmentDetailController extends Controller
{
    public function index()
    {
        $details = EnrollmentDetail::with([
            'enrollment.student.person',
            'enrollment.academicPeriod',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $details,
        ]);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'enrollment_id' => 'required|exists:enrollments,id',
        'academic_offering_id' => 'required|exists:academic_offerings,id',
        'status' => 'required|in:active,cancelled,completed',
    ]);

    return DB::transaction(function () use ($validated) {

        // Bloquear la oferta para evitar sobrecupos simultáneos
        $offering = AcademicOffering::whereKey(
            $validated['academic_offering_id']
        )->lockForUpdate()->firstOrFail();

        $enrollment = Enrollment::with('academicRecord')
            ->findOrFail($validated['enrollment_id']);

        // 1. Validar el período académico
        if ($enrollment->academic_period_id !== $offering->academic_period_id) {
            return response()->json([
                'success' => false,
                'message' => 'La oferta académica no pertenece al período de la matrícula.',
            ], 422);
        }

        // 2. Validar el plan académico
        $academicPlanId = $enrollment->academicRecord?->academic_plan_id;

        if (!$academicPlanId) {
            return response()->json([
                'success' => false,
                'message' => 'La matrícula no tiene un plan académico asociado.',
            ], 422);
        }

        $belongsToPlan = AcademicCurriculumItem::where(
            'academic_plan_id',
            $academicPlanId
        )
            ->where('academic_unit_id', $offering->academic_unit_id)
            ->exists();

        if (!$belongsToPlan) {
            return response()->json([
                'success' => false,
                'message' => 'La asignatura no pertenece al plan académico del estudiante.',
            ], 422);
        }

        // 3. Evitar ofertas duplicadas
        $duplicate = EnrollmentDetail::where(
            'enrollment_id',
            $validated['enrollment_id']
        )
            ->where('academic_offering_id', $offering->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta académica ya está registrada en la matrícula.',
            ], 422);
        }

        // 4. Verificar que la oferta esté activa
        if (!$offering->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'La oferta académica no está activa.',
            ], 422);
        }

        // 5. Comprobar las vacantes disponibles
        if ($validated['status'] === 'active') {

            $occupied = EnrollmentDetail::where(
                'academic_offering_id',
                $offering->id
            )
                ->where('status', 'active')
                ->count();

            if ($occupied >= $offering->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay vacantes disponibles en esta sección.',
                ], 422);
            }
        }

        // 6. Crear el detalle
        $detail = EnrollmentDetail::create($validated);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ], 201);
    });
}

    public function show($id)
    {
        $detail = EnrollmentDetail::with([
            'enrollment.student.person',
            'enrollment.academicPeriod',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    public function update(Request $request, $id)
{
    $validated = $request->validate([
        'enrollment_id' => 'required|exists:enrollments,id',
        'academic_offering_id' => 'required|exists:academic_offerings,id',
        'status' => 'required|in:active,cancelled,completed',
    ]);

    return DB::transaction(function () use ($id, $validated) {

        // Consultar la oferta actual antes de bloquear
        $currentOfferingId = EnrollmentDetail::findOrFail($id)
            ->academic_offering_id;

        $newOfferingId = $validated['academic_offering_id'];

        // Bloquear ambas ofertas en orden ascendente
        $offeringIds = collect([
            $currentOfferingId,
            $newOfferingId,
        ])->unique()->sort()->values();

        $offerings = AcademicOffering::whereIn('id', $offeringIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $offering = $offerings->get($newOfferingId);

        if (!$offering) {
            abort(404, 'Oferta académica no encontrada.');
        }

        // Consultar nuevamente el detalle bajo los bloqueos
        $detail = EnrollmentDetail::findOrFail($id);

        if ((int) $detail->academic_offering_id !== (int) $currentOfferingId) {
            return response()->json([
                'success' => false,
                'message' => 'La inscripción fue modificada. Intenta nuevamente.',
            ], 409);
        }

        $enrollment = Enrollment::with('academicRecord')
            ->findOrFail($validated['enrollment_id']);

        // 1. Validar período académico
        if ($enrollment->academic_period_id !== $offering->academic_period_id) {
            return response()->json([
                'success' => false,
                'message' => 'La oferta académica no pertenece al período de la matrícula.',
            ], 422);
        }

        // 2. Validar plan académico
        $academicPlanId = $enrollment->academicRecord?->academic_plan_id;

        if (!$academicPlanId) {
            return response()->json([
                'success' => false,
                'message' => 'La matrícula no tiene un plan académico asociado.',
            ], 422);
        }

        $belongsToPlan = AcademicCurriculumItem::where(
            'academic_plan_id',
            $academicPlanId
        )
            ->where('academic_unit_id', $offering->academic_unit_id)
            ->exists();

        if (!$belongsToPlan) {
            return response()->json([
                'success' => false,
                'message' => 'La asignatura no pertenece al plan académico del estudiante.',
            ], 422);
        }

        // 3. Evitar duplicados
        $duplicate = EnrollmentDetail::where(
            'enrollment_id',
            $validated['enrollment_id']
        )
            ->where('academic_offering_id', $newOfferingId)
            ->where('id', '!=', $detail->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta académica ya está registrada en la matrícula.',
            ], 422);
        }

        // 4. Determinar si se necesita una nueva vacante
        $needsVacancy = $validated['status'] === 'active'
            && (
                $detail->status !== 'active'
                || (int) $detail->academic_offering_id !== (int) $newOfferingId
            );

        if ($needsVacancy) {

            if (!$offering->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'La oferta académica no está activa.',
                ], 422);
            }

            $occupied = EnrollmentDetail::where(
                'academic_offering_id',
                $newOfferingId
            )
                ->where('status', 'active')
                ->count();

            if ($occupied >= $offering->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay vacantes disponibles en esta sección.',
                ], 422);
            }
        }

        // 5. Actualizar inscripción
        $detail->update($validated);

        return response()->json([
            'success' => true,
            'data' => $detail->fresh(),
        ]);
    });
}

    public function destroy($id)
{
    return DB::transaction(function () use ($id) {

        // Consultar la oferta asociada al detalle
        $offeringId = EnrollmentDetail::findOrFail($id)
            ->academic_offering_id;

        // Bloquear la oferta académica
        AcademicOffering::whereKey($offeringId)
            ->lockForUpdate()
            ->firstOrFail();

        // Volver a consultar el detalle dentro de la transacción
        $detail = EnrollmentDetail::findOrFail($id);

        // Evitar eliminarlo si fue trasladado a otra oferta
        if ((int) $detail->academic_offering_id !== (int) $offeringId) {
            return response()->json([
                'success' => false,
                'message' => 'La inscripción fue modificada. Intenta nuevamente.',
            ], 409);
        }

        $detail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Detalle de matrícula eliminado correctamente',
        ]);
    });
}
}
