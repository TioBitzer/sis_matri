<?php

namespace App\Http\Controllers;

use App\Models\AcademicUnit;
use App\Models\Institution;
use Illuminate\Http\Request;

class AcademicUnitController extends Controller
{
    public function index()
    {
        $units = AcademicUnit::with('institution')
            ->orderBy('name')
            ->get();

        return view('academic-units.index', compact('units'));
    }

    public function create()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-units.create', compact('institutions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'unit_type' => 'required|string|max:50',
            'credits' => 'required|integer|min:0',
            'hours' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicUnit::create($validated);

        return redirect()
            ->route('academic-units.index')
            ->with('success', 'Unidad académica creada correctamente.');
    }

    public function show(string $id)
    {
        $unit = AcademicUnit::with('institution')
            ->findOrFail($id);

        return view('academic-units.show', compact('unit'));
    }

    public function edit(string $id)
    {
        $unit = AcademicUnit::findOrFail($id);

        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-units.edit', compact('unit', 'institutions'));
    }

    public function update(Request $request, string $id)
    {
        $unit = AcademicUnit::findOrFail($id);

        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'unit_type' => 'required|string|max:50',
            'credits' => 'required|integer|min:0',
            'hours' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $unit->update($validated);

        return redirect()
            ->route('academic-units.index')
            ->with('success', 'Unidad académica actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        $unit = AcademicUnit::findOrFail($id);

        $unit->delete();

        return redirect()
            ->route('academic-units.index')
            ->with('success', 'Unidad académica eliminada correctamente.');
    }
}