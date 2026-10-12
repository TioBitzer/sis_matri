<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with('person')->get();

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function show(string $id)
    {
        $student = Student::with('person')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);
    }

    public function store(StoreStudentRequest $request)
    {


        $validated = $request->validated();

        $student = Student::create($validated);

        return response()->json([
            'success' => true,
            'data' => $student,
        ], 201);
    }

    public function update(UpdateStudentRequest $request, string $id)
    {
        $student = Student::findOrFail($id);

       $validated = $request->validated();

        $student->update($validated);

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);

    }
    public function destroy(string $id)
{
    $student = Student::findOrFail($id);

    $student->delete();

    return response()->json([
        'success' => true,
        'message' => 'Estudiante eliminado correctamente',
    ]);
}
}