<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use Illuminate\Http\Request;



class AcademicRecordController extends Controller
{
    public function index()
    {
        $records = AcademicRecord::with([
            'student.person',
            'academicProgram',
            'academicPlan',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_program_id' => 'required|exists:academic_programs,id',
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|max:50',
        ]);

        $record = AcademicRecord::create($validated);

        return response()->json([
            'success' => true,
            'data' => $record,
        ], 201);
    }

    public function show($id)
    {
        $record = AcademicRecord::with([
            'student.person',
            'academicProgram',
            'academicPlan',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function update(Request $request, $id)
    {
        $record = AcademicRecord::findOrFail($id);

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_program_id' => 'required|exists:academic_programs,id',
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|string|max:50',
        ]);

        $record->update($validated);

        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    public function destroy($id)
    {
        $record = AcademicRecord::findOrFail($id);

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro académico eliminado correctamente',
        ]);
    }
}
