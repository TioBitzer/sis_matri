<?php

namespace App\Http\Controllers;

use App\Models\AcademicProgram;
use App\Models\Institution;
use Illuminate\Http\Request;

class AcademicProgramController extends Controller
{
    public function index()
    {
        $programs = AcademicProgram::with('institution')
            ->orderBy('name')
            ->get();

        return view('academic-programs.index', compact('programs'));
    }

    public function create()
    {
        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-programs.create', compact('institutions'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicProgram::create($validated);

        return redirect()
            ->route('academic-programs.index')
            ->with('success', 'Programa académico creado correctamente.');
    }

    public function show(string $id)
    {
        $program = AcademicProgram::with('institution')
            ->findOrFail($id);

        return view('academic-programs.show', compact('program'));
    }

    public function edit(string $id)
    {
        $program = AcademicProgram::findOrFail($id);

        $institutions = Institution::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-programs.edit', compact('program', 'institutions'));
    }

    public function update(Request $request, string $id)
    {
        $program = AcademicProgram::findOrFail($id);

        $validated = $request->validate([
            'institution_id' => 'required|exists:institutions,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $program->update($validated);

        return redirect()
            ->route('academic-programs.index')
            ->with('success', 'Programa académico actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $program = AcademicProgram::findOrFail($id);

        $program->delete();

        return redirect()
            ->route('academic-programs.index')
            ->with('success', 'Programa académico eliminado correctamente.');
    }
}