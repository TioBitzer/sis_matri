<?php

namespace App\Http\Controllers;



use App\Models\Enrollment;
use App\Models\Student;
use App\Models\AcademicPeriod;
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
            'academicRecord.academicPlan'
        ])
        ->orderByDesc('enrollment_date')
        ->get();

        return view('enrollments.index', compact('enrollments'));
    }

    public function create()
    {
        $students = Student::with('person')
            ->where('status', 'active')
            ->orderBy('student_code')
            ->get();

        $periods = AcademicPeriod::where('is_active', true)
            ->orderBy('start_date')
            ->get();

        $academicRecords = AcademicRecord::with([
            'student.person',
            'academicProgram',
            'academicPlan'
        ])
        ->where('status', 'active')
        ->get();

        return view(
            'enrollments.create',
            compact('students', 'periods', 'academicRecords')
        );
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

        // Verificar que el historial académico pertenezca al estudiante
        $academicRecord = AcademicRecord::findOrFail(
            $validated['academic_record_id']
        );

        if ($academicRecord->student_id != $validated['student_id']) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_record_id' =>
                        'El historial académico no pertenece al estudiante seleccionado.'
                ]);
        }
        $alreadyEnrolled = Enrollment::where('student_id', $validated['student_id'])
    ->where('academic_period_id', $validated['academic_period_id'])
    ->exists();

if ($alreadyEnrolled) {
    return back()
        ->withInput()
        ->withErrors([
            'academic_period_id' =>
                'El estudiante ya tiene una matrícula registrada en este período académico.'
        ]);
}

        Enrollment::create($validated);

        return redirect()
            ->route('enrollments.index')
            ->with('success', 'Matrícula creada correctamente.');
    }

    public function show(string $id)
    {
        $enrollment = Enrollment::with([
            'student.person',
            'academicPeriod',
            'academicRecord.academicProgram',
            'academicRecord.academicPlan',
            'enrollmentDetails.academicOffering.academicUnit'
        ])->findOrFail($id);

        return view('enrollments.show', compact('enrollment'));
    }

    public function edit(string $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $students = Student::with('person')
            ->where('status', 'active')
            ->orderBy('student_code')
            ->get();

        $periods = AcademicPeriod::where('is_active', true)
            ->orderBy('start_date')
            ->get();

        $academicRecords = AcademicRecord::with([
            'student.person',
            'academicProgram',
            'academicPlan'
        ])
        ->where('status', 'active')
        ->get();

        return view(
            'enrollments.edit',
            compact(
                'enrollment',
                'students',
                'periods',
                'academicRecords'
            )
        );
    }

    public function update(Request $request, string $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_period_id' => 'required|exists:academic_periods,id',
            'academic_record_id' => 'required|exists:academic_records,id',
            'enrollment_date' => 'required|date',
            'status' => 'required|string|max:50',
        ]);

        // Verificar que el historial académico pertenezca al estudiante
        $academicRecord = AcademicRecord::findOrFail(
            $validated['academic_record_id']
        );

        if ($academicRecord->student_id != $validated['student_id']) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_record_id' =>
                        'El historial académico no pertenece al estudiante seleccionado.'
                ]);
        }
           $alreadyEnrolled = Enrollment::where('student_id', $validated['student_id'])
    ->where('academic_period_id', $validated['academic_period_id'])
    ->where('id', '!=', $enrollment->id)
    ->exists();

if ($alreadyEnrolled) {
    return back()
        ->withInput()
        ->withErrors([
            'academic_period_id' =>
                'El estudiante ya tiene otra matrícula registrada en este período académico.'
        ]);
}
        $enrollment->update($validated);

        return redirect()
            ->route('enrollments.index')
            ->with('success', 'Matrícula actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        $enrollment = Enrollment::findOrFail($id);

        $enrollment->delete();

        return redirect()
            ->route('enrollments.index')
            ->with('success', 'Matrícula eliminada correctamente.');
    }
}
