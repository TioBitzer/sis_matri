<?php

namespace App\Http\Controllers\Api;
use App\Models\AcademicCurriculumItem;
use App\Http\Controllers\Controller;
use App\Models\EnrollmentDetail;
use Illuminate\Http\Request;
use App\Models\Enrollment;
use App\Models\AcademicOffering;



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
            'status' => 'required|string|max:50',
        ]);

        // 1. Obtener la matrícula y la oferta académica
        $enrollment = Enrollment::with('academicRecord')
            ->findOrFail($validated['enrollment_id']);

        $offering = AcademicOffering::findOrFail(
            $validated['academic_offering_id']
        );

        // 2. Validar que los períodos académicos coincidan
        if ($enrollment->academic_period_id !== $offering->academic_period_id) {
            return response()->json([
                'success' => false,
                'message' => 'La oferta académica no pertenece al período de la matrícula.',
            ], 422);
        }

        // 3. Obtener el plan académico del estudiante
        $academicPlanId = $enrollment->academicRecord?->academic_plan_id;

        if (!$academicPlanId) {
            return response()->json([
                'success' => false,
                'message' => 'La matrícula no tiene un plan académico asociado.',
            ], 422);
        }

        // 4. Comprobar que la asignatura pertenece al plan académico
        $belongsToPlan = AcademicCurriculumItem::where(
            'academic_plan_id',
            $academicPlanId
        )
        ->where(
            'academic_unit_id',
            $offering->academic_unit_id
        )
        ->exists();

        if (!$belongsToPlan) {
            return response()->json([
                'success' => false,
                'message' => 'La asignatura no pertenece al plan académico del estudiante.',
            ], 422);
        }

        // 5. Evitar registrar dos veces la misma oferta académica
        $exists = EnrollmentDetail::where(
            'enrollment_id',
            $validated['enrollment_id']
        )
        ->where(
            'academic_offering_id',
            $validated['academic_offering_id']
        )
        ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Esta oferta académica ya está registrada en la matrícula.',
            ], 422);
        }

        // 6. Crear el detalle de matrícula
        $detail = EnrollmentDetail::create($validated);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ], 201);
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
    $detail = EnrollmentDetail::findOrFail($id);

    $validated = $request->validate([
        'enrollment_id' => 'required|exists:enrollments,id',
        'academic_offering_id' => 'required|exists:academic_offerings,id',
        'status' => 'required|string|max:50',
    ]);

    $enrollment = Enrollment::with('academicRecord')
        ->findOrFail($validated['enrollment_id']);

    $offering = AcademicOffering::findOrFail(
        $validated['academic_offering_id']
    );

    // 1. Validar que coincidan los períodos académicos
    if ($enrollment->academic_period_id !== $offering->academic_period_id) {
        return response()->json([
            'success' => false,
            'message' => 'La oferta académica no pertenece al período de la matrícula.',
        ], 422);
    }

    // 2. Obtener el plan académico del estudiante
    $academicPlanId = $enrollment->academicRecord?->academic_plan_id;

    if (!$academicPlanId) {
        return response()->json([
            'success' => false,
            'message' => 'La matrícula no tiene un plan académico asociado.',
        ], 422);
    }

    // 3. Verificar que la asignatura pertenezca al plan
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

    // 4. Evitar duplicados, excluyendo el registro actual
    $duplicate = EnrollmentDetail::where(
        'enrollment_id',
        $validated['enrollment_id']
    )
        ->where('academic_offering_id', $validated['academic_offering_id'])
        ->where('id', '!=', $detail->id)
        ->exists();

    if ($duplicate) {
        return response()->json([
            'success' => false,
            'message' => 'Esta oferta académica ya está registrada en la matrícula.',
        ], 422);
    }

    // 5. Actualizar el detalle
    $detail->update($validated);

    return response()->json([
        'success' => true,
        'data' => $detail->fresh(),
    ]);
}

    public function destroy($id)
    {
        $detail = EnrollmentDetail::findOrFail($id);

        $detail->delete();

        return response()->json([
            'success' => true,
            'message' => 'Detalle de matrícula eliminado correctamente',
        ]);
    }
}
