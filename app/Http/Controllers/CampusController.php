<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\Institution;
use Illuminate\Http\Request;

class CampusController extends Controller
{
    public function index()
    {
        $campuses = Campus::with('institution')
            ->orderBy('name')
            ->get();

        return view('campuses.index', compact('campuses'));
    }

    public function create()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('campuses.create', compact('institutions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Campus::create($validated);

        return redirect()
            ->route('campuses.index')
            ->with('success', 'Sede creada correctamente.');
    }

    public function show(string $id)
    {
        $campus = Campus::with('institution')->findOrFail($id);

        return view('campuses.show', compact('campus'));
    }

    public function edit(string $id)
    {
        $campus = Campus::findOrFail($id);

        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('campuses.edit', compact('campus', 'institutions'));
    }

    public function update(Request $request, string $id)
    {
        $campus = Campus::findOrFail($id);

        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $campus->update($validated);

        return redirect()
            ->route('campuses.index')
            ->with('success', 'Sede actualizada correctamente.');
    }

    public function destroy(string $id)
    {
        $campus = Campus::findOrFail($id);

        $campus->delete();

        return redirect()
            ->route('campuses.index')
            ->with('success', 'Sede eliminada correctamente.');
    }
}