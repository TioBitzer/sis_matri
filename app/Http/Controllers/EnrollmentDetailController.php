<?php

namespace App\Http\Controllers;


use App\Models\EnrollmentDetail;
use App\Models\Enrollment;
use App\Models\AcademicOffering;
use Illuminate\Http\Request;

class EnrollmentDetailController extends Controller
{
    public function index()
    {
        $details = EnrollmentDetail::with([
            'enrollment.student.person',
            'enrollment.academicPeriod',
            'academicOffering.academicUnit',
            'academicOffering.campus'
        ])
        ->orderBy('enrollment_id')
        ->get();

        return view('enrollment-details.index', compact('details'));
    }

    public function create()
    {
        $enrollments = Enrollment::with([
            'student.person',
            'academicPeriod'
        ])
        ->where('status', 'active')
        ->orderByDesc('enrollment_date')
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
            'enrollment-details.create',
            compact('enrollments', 'offerings')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'status' => 'required|string|max:50',
        ]);

        $enrollment = Enrollment::findOrFail(
            $validated['enrollment_id']
        );

        $offering = AcademicOffering::findOrFail(
            $validated['academic_offering_id']
        );

        // Verificar que matrícula y oferta pertenezcan al mismo período
        if (
            $enrollment->academic_period_id !==
            $offering->academic_period_id
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_offering_id' =>
                        'El periodo de la oferta académica no coincide con el periodo de la matrícula.'
                ]);
        }

        // Evitar que el estudiante se matricule dos veces
        // en la misma oferta académica
        $alreadyEnrolled = EnrollmentDetail::where(
            'enrollment_id',
            $enrollment->id
        )
        ->where(
            'academic_offering_id',
            $offering->id
        )
        ->exists();

        if ($alreadyEnrolled) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_offering_id' =>
                        'El estudiante ya está matriculado en esta oferta académica.'
                ]);
        }

        // Verificar capacidad de la oferta académica
        $occupied = EnrollmentDetail::where(
            'academic_offering_id',
            $offering->id
        )
        ->whereIn('status', ['active', 'enrolled'])
        ->count();

        if ($occupied >= $offering->capacity) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_offering_id' =>
                        'La oferta académica ha alcanzado su capacidad máxima.'
                ]);
        }

        EnrollmentDetail::create($validated);

        return redirect()
            ->route('enrollment-details.index')
            ->with(
                'success',
                'Detalle de matrícula agregado correctamente.'
            );
    }

    public function show(string $id)
    {
        $detail = EnrollmentDetail::with([
            'enrollment.student.person',
            'enrollment.academicPeriod',
            'academicOffering.academicUnit',
            'academicOffering.campus'
        ])->findOrFail($id);

        return view(
            'enrollment-details.show',
            compact('detail')
        );
    }

    public function edit(string $id)
    {
        $detail = EnrollmentDetail::findOrFail($id);

        $enrollments = Enrollment::with([
            'student.person',
            'academicPeriod'
        ])
        ->where('status', 'active')
        ->orderByDesc('enrollment_date')
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
            'enrollment-details.edit',
            compact('detail', 'enrollments', 'offerings')
        );
    }

    public function update(Request $request, string $id)
    {
        $detail = EnrollmentDetail::findOrFail($id);

        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'academic_offering_id' => 'required|exists:academic_offerings,id',
            'status' => 'required|string|max:50',
        ]);

        $enrollment = Enrollment::findOrFail(
            $validated['enrollment_id']
        );

        $offering = AcademicOffering::findOrFail(
            $validated['academic_offering_id']
        );

        // Verificar que matrícula y oferta pertenezcan al mismo período
        if (
            $enrollment->academic_period_id !==
            $offering->academic_period_id
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_offering_id' =>
                        'El periodo de la oferta académica no coincide con el periodo de la matrícula.'
                ]);
        }

        // Evitar duplicado, excluyendo el detalle actual
        $alreadyEnrolled = EnrollmentDetail::where(
            'enrollment_id',
            $enrollment->id
        )
        ->where(
            'academic_offering_id',
            $offering->id
        )
        ->where('id', '!=', $detail->id)
        ->exists();

        if ($alreadyEnrolled) {
            return back()
                ->withInput()
                ->withErrors([
                    'academic_offering_id' =>
                        'El estudiante ya está matriculado en esta oferta académica.'
                ]);
        }

        // Verificar capacidad solamente si estamos cambiando
        // hacia una oferta diferente
        if ($detail->academic_offering_id != $offering->id) {

            $occupied = EnrollmentDetail::where(
                'academic_offering_id',
                $offering->id
            )
            ->whereIn('status', ['active', 'enrolled'])
            ->count();

            if ($occupied >= $offering->capacity) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'academic_offering_id' =>
                            'La oferta académica ha alcanzado su capacidad máxima.'
                    ]);
            }
        }

        $detail->update($validated);

        return redirect()
            ->route('enrollment-details.index')
            ->with(
                'success',
                'Detalle de matrícula actualizado correctamente.'
            );
    }

    public function destroy(string $id)
    {
        $detail = EnrollmentDetail::findOrFail($id);

        $detail->delete();

        return redirect()
            ->route('enrollment-details.index')
            ->with(
                'success',
                'Detalle de matrícula eliminado correctamente.'
            );
    }
}
