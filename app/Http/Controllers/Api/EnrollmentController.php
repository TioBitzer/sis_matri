<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\AcademicRecord;
use Illuminate\Http\Request;



class EnrollmentController extends Controller
{
    public function index()
    {
        $enrollments = Enrollment::with([
            'student.person',
            'academicPeriod',
            'academicRecord.academicProgram',
            'academicRecord.academicPlan',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $enrollments,
        ]);
    }

    public function store(Request $request)
{

    $validated = $request->validate([
        'student_id' => 'required|exists:students,id',
        'academic_period_id' => 'required|exists:academic_periods,id',
        'academic_record_id' => 'required|exists:academic_records,id',
        'enrollment_date' => 'required|date',
        'status' => 'required|string|max:50',
    ]);


    $academicRecord = AcademicRecord::findOrFail(
        $validated['academic_record_id']
    );

    if ($academicRecord->student_id != $validated['student_id']) {
        return response()->json([
            'success' => false,
            'message' => 'El expediente académico no pertenece al estudiante seleccionado.',
        ], 422);
    }


    $exists = Enrollment::where(
        'academic_record_id',
        $validated['academic_record_id']
    )
    ->where(
        'academic_period_id',
        $validated['academic_period_id']
    )
    ->exists();

    if ($exists) {
        return response()->json([
            'success' => false,
            'message' => 'Este expediente académico ya tiene una matrícula registrada en el periodo seleccionado.',
        ], 422);
    }


    $enrollment = Enrollment::create($validated);

    // 5. Devolver la respuesta
    return response()->json([
        'success' => true,
        'data' => $enrollment,
    ], 201);
}

    public function show($id)
    {
        $enrollment = Enrollment::with([
            'student.person',
            'academicPeriod',
            'academicRecord.academicProgram',
            'academicRecord.academicPlan',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

   public function update(Request $request, $id)
{
    $enrollment = Enrollment::findOrFail($id);

    $validated = $request->validate([
        'student_id' => 'required|exists:students,id',
        'academic_period_id' => 'required|exists:academic_periods,id',
        'academic_record_id' => 'required|exists:academic_records,id',
        'enrollment_date' => 'required|date',
        'status' => 'required|string|max:50',
    ]);


    $academicRecord = AcademicRecord::findOrFail(
        $validated['academic_record_id']
    );

    if ($academicRecord->student_id != $validated['student_id']) {
        return response()->json([
            'success' => false,
            'message' => 'El expediente académico no pertenece al estudiante seleccionado.',
        ], 422);
    }


    $exists = Enrollment::where(
        'academic_record_id',
        $validated['academic_record_id']
    )
    ->where(
        'academic_period_id',
        $validated['academic_period_id']
    )
    ->where('id', '!=', $enrollment->id)
    ->exists();

    if ($exists) {
        return response()->json([
            'success' => false,
            'message' => 'Este expediente académico ya tiene una matrícula registrada en el periodo seleccionado.',
        ], 422);
    }

    $enrollment->update($validated);

    return response()->json([
        'success' => true,
        'data' => $enrollment,
    ]);
}
    public function destroy($id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Matrícula eliminada correctamente',
        ]);
    }
}
