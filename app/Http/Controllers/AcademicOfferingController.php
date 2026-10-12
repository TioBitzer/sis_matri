<?php

namespace App\Http\Controllers;

use App\Models\AcademicOffering;
use App\Models\AcademicPeriod;
use App\Models\AcademicUnit;
use App\Models\Campus;
use Illuminate\Http\Request;

class AcademicOfferingController extends Controller
{
    public function index()
    {
        $offerings = AcademicOffering::with([
            'academicUnit',
            'academicPeriod',
            'campus'
        ])
        ->orderBy('academic_period_id')
        ->orderBy('academic_unit_id')
        ->orderBy('section')
        ->get();

        return view('academic-offerings.index', compact('offerings'));
    }

    public function create()
    {
        $units = AcademicUnit::where('is_active', true)
            ->orderBy('name')
            ->get();

        $periods = AcademicPeriod::where('is_active', true)
            ->orderBy('start_date')
            ->get();

        $campuses = Campus::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'academic-offerings.create',
            compact('units', 'periods', 'campuses')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_unit_id' => 'required|exists:academic_units,id',
            'academic_period_id' => 'required|exists:academic_periods,id',
            'campus_id' => 'required|exists:campuses,id',
            'section' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicOffering::create($validated);

        return redirect()
            ->route('academic-offerings.index')
            ->with('success', 'Oferta académica creada correctamente.');
    }

    public function show(string $id)
    {
        $offering = AcademicOffering::with([
            'academicUnit',
            'academicPeriod',
            'campus'
        ])->findOrFail($id);

        return view('academic-offerings.show', compact('offering'));
    }

    public function edit(string $id)
    {
        $offering = AcademicOffering::findOrFail($id);

        $units = AcademicUnit::where('is_active', true)
            ->orderBy('name')
            ->get();

        $periods = AcademicPeriod::where('is_active', true)
            ->orderBy('start_date')
            ->get();

        $campuses = Campus::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'academic-offerings.edit',
            compact('offering', 'units', 'periods', 'campuses')
        );
    }

    public function update(Request $request, string $id)
    {
        $offering = AcademicOffering::findOrFail($id);

        $validated = $request->validate([
            'academic_unit_id' => 'required|exists:academic_units,id',
            'academic_period_id' => 'required|exists:academic_periods,id',
            'campus_id' => 'required|exists:campuses,id',
            'section' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $offering->update($validated);

        return redirect()
            ->route('academic-offerings.index')
            ->with('success', 'Oferta académica actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        $offering = AcademicOffering::findOrFail($id);

        $offering->delete();

        return redirect()
            ->route('academic-offerings.index')
            ->with('success', 'Oferta académica eliminada correctamente.');
    }
}