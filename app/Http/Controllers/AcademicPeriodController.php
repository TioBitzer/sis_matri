<?php

namespace App\Http\Controllers;

use App\Models\AcademicPeriod;
use App\Models\Institution;
use Illuminate\Http\Request;

class AcademicPeriodController extends Controller
{
    public function index()
    {
        $periods = AcademicPeriod::with('institution')
            ->orderBy('start_date')
            ->get();

        return view('academic-periods.index', compact('periods'));
    }

    public function create()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-periods.create', compact('institutions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicPeriod::create($validated);

        return redirect()
            ->route('academic-periods.index')
            ->with('success', 'Periodo académico creado correctamente.');
    }

    public function show(string $id)
    {
        $period = AcademicPeriod::with('institution')
            ->findOrFail($id);

        return view('academic-periods.show', compact('period'));
    }

    public function edit(string $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-periods.edit', compact('period', 'institutions'));
    }

    public function update(Request $request, string $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $period->update($validated);

        return redirect()
            ->route('academic-periods.index')
            ->with('success', 'Periodo académico actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        $period->delete();

        return redirect()
            ->route('academic-periods.index')
            ->with('success', 'Periodo académico eliminado correctamente.');
    }
}