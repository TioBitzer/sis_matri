<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentDetail;
use Illuminate\Http\Request;


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

        $detail->update($validated);

        return response()->json([
            'success' => true,
            'data' => $detail,
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
