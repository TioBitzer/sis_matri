<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::orderBy('name')->get();

        return view('institutions.index', compact('institutions'));
    }

    public function create()
    {
        return view('institutions.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:institutions,code',
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Institution::create($validated);

        return redirect()
            ->route('institutions.index')
            ->with('success', 'Institución creada correctamente.');
    }

    public function show(string $id)
    {
        $institution = Institution::findOrFail($id);

        return view('institutions.show', compact('institution'));
    }

    public function edit(string $id)
    {
        $institution = Institution::findOrFail($id);

        return view('institutions.edit', compact('institution'));
    }

    public function update(Request $request, string $id)
    {
        $institution = Institution::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:institutions,code,' . $institution->id,
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $institution->update($validated);

        return redirect()
            ->route('institutions.index')
            ->with('success', 'Institución actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        $institution = Institution::findOrFail($id);

        $institution->delete();

        return redirect()
            ->route('institutions.index')
            ->with('success', 'Institución eliminada correctamente.');
    }
}