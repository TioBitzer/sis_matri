<?php

namespace App\Http\Controllers;

use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use Illuminate\Http\Request;

class AcademicLevelController extends Controller
{
    public function index()
    {
        $levels = AcademicLevel::with('academicPlan')
            ->orderBy('academic_plan_id')
            ->orderBy('order')
            ->get();

        return view('academic-levels.index', compact('levels'));
    }

    public function create()
    {
        $plans = AcademicPlan::where('is_active', true)
            ->with('academicProgram')
            ->orderBy('name')
            ->get();

        return view('academic-levels.create', compact('plans'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'name' => 'required|string|max:255',
            'order' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicLevel::create($validated);

        return redirect()
            ->route('academic-levels.index')
            ->with('success', 'Nivel académico creado correctamente.');
    }

    public function show(string $id)
    {
        $level = AcademicLevel::with('academicPlan.academicProgram')
            ->findOrFail($id);

        return view('academic-levels.show', compact('level'));
    }

    public function edit(string $id)
    {
        $level = AcademicLevel::findOrFail($id);

        $plans = AcademicPlan::where('is_active', true)
            ->with('academicProgram')
            ->orderBy('name')
            ->get();

        return view('academic-levels.edit', compact('level', 'plans'));
    }

    public function update(Request $request, string $id)
    {
        $level = AcademicLevel::findOrFail($id);

        $validated = $request->validate([
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'name' => 'required|string|max:255',
            'order' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $level->update($validated);

        return redirect()
            ->route('academic-levels.index')
            ->with('success', 'Nivel académico actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $level = AcademicLevel::findOrFail($id);

        $level->delete();

        return redirect()
            ->route('academic-levels.index')
            ->with('success', 'Nivel académico eliminado correctamente.');
    }
}