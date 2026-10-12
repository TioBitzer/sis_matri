<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with(['person', 'institution'])->get();

        return response()->json([
            'success' => true,
            'data' => $teachers,
        ]);
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'person_id' => 'required|exists:people,id',
        'institution_id' => 'required|exists:institutions,id',
        'employee_code' => 'nullable|string|max:50',
        'specialization' => 'nullable|string|max:255',
        'is_active' => 'boolean',
    ]);

    $teacher = Teacher::create($validated);

    return response()->json([
        'success' => true,
        'data' => $teacher,
    ], 201);
}
public function show($id)
{
    $teacher = Teacher::with(['person', 'institution'])->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $teacher,
    ]);
}
public function update(Request $request, $id)
{
    $teacher = Teacher::findOrFail($id);

    $validated = $request->validate([
        'person_id' => 'required|exists:people,id',
        'institution_id' => 'required|exists:institutions,id',
        'employee_code' => 'nullable|string|max:50',
        'specialization' => 'nullable|string|max:255',
        'is_active' => 'boolean',
    ]);

    $teacher->update($validated);

    return response()->json([
        'success' => true,
        'data' => $teacher,
    ]);
}
public function destroy($id)
{
    $teacher = Teacher::findOrFail($id);

    $teacher->delete();

    return response()->json([
        'success' => true,
        'message' => 'Profesor eliminado correctamente',
    ]);
}
}