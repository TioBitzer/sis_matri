<?php

namespace App\Http\Controllers;

use App\Models\TeachingAssignment;
use App\Models\Teacher;
use App\Models\AcademicOffering;
use Illuminate\Http\Request;

class TeachingAssignmentController extends Controller
{
    public function index()
    {
        $assignments = TeachingAssignment::with([
            'teacher.person',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus'
        ])
        ->orderBy('academic_offering_id')
        ->get();

        return view(
            'teaching-assignments.index',
            compact('assignments')
        );
    }

    public function create()
    {
        $teachers = Teacher::with('person')
            ->where('is_active', true)
            ->orderBy('employee_code')
            ->get();

        $offerings = AcademicOffering::with([
            'academicUnit',
            'academicPeriod',
            'campus'
        ])
        ->where('is_active', true)
        ->orderBy('academic_period_id')
        ->get();

        return view(
            'teaching-assignments.create',
            compact('teachers', 'offerings')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'role' => 'required|string|max:50',
        ]);

        TeachingAssignment::create($validated);

        return redirect()
            ->route('teaching-assignments.index')
            ->with(
                'success',
                'Asignación docente creada correctamente.'
            );
    }

    public function show(string $id)
    {
        $assignment = TeachingAssignment::with([
            'teacher.person',
            'academicOffering.academicUnit',
            'academicOffering.academicPeriod',
            'academicOffering.campus'
        ])->findOrFail($id);

        return view(
            'teaching-assignments.show',
            compact('assignment')
        );
    }

    public function edit(string $id)
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $teachers = Teacher::with('person')
            ->where('is_active', true)
            ->orderBy('employee_code')
            ->get();

        $offerings = AcademicOffering::with([
            'academicUnit',
            'academicPeriod',
            'campus'
        ])
        ->where('is_active', true)
        ->orderBy('academic_period_id')
        ->get();

        return view(
            'teaching-assignments.edit',
            compact('assignment', 'teachers', 'offerings')
        );
    }

    public function update(Request $request, string $id)
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'role' => 'required|string|max:50',
        ]);

        $assignment->update($validated);

        return redirect()
            ->route('teaching-assignments.index')
            ->with(
                'success',
                'Asignación docente actualizada correctamente.'
            );
    }

    public function destroy(string $id)
    {
        $assignment = TeachingAssignment::findOrFail($id);

        $assignment->delete();

        return redirect()
            ->route('teaching-assignments.index')
            ->with(
                'success',
                'Asignación docente eliminada correctamente.'
            );
    }
}