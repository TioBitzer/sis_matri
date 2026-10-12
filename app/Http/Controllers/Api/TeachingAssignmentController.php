<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;


class TeachingAssignmentController extends Controller
{
    public function index()
    {
        $assignments = TeachingAssignment::with([
            'teacher.person',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'role' => 'nullable|string|max:100',
        ]);

        $assignment = TeachingAssignment::create($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ], 201);
    }

    public function show($id)
    {
        $assignment = TeachingAssignment::with([
            'teacher.person',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'role' => 'nullable|string|max:100',
        ]);

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    public function destroy($id)
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación docente eliminada correctamente',
        ]);
    }
}
