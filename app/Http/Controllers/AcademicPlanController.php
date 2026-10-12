<?php

namespace App\Http\Controllers;

use App\Models\AcademicPlan;
use App\Models\AcademicProgram;
use Illuminate\Http\Request;

class AcademicPlanController extends Controller
{
    public function index()
    {
        $plans = AcademicPlan::with('academicProgram')
            ->orderBy('name')
            ->get();

        return view('academic-plans.index', compact('plans'));
    }

    public function create()
    {
        $programs = AcademicProgram::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-plans.create', compact('programs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_program_id' => 'required|exists:academic_programs,id',
            'name' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AcademicPlan::create($validated);

        return redirect()
            ->route('academic-plans.index')
            ->with('success', 'Plan académico creado correctamente.');
    }

    public function show(string $id)
    {
        $plan = AcademicPlan::with('academicProgram')
            ->findOrFail($id);

        return view('academic-plans.show', compact('plan'));
    }

    public function edit(string $id)
    {
        $plan = AcademicPlan::findOrFail($id);

        $programs = AcademicProgram::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('academic-plans.edit', compact('plan', 'programs'));
    }

    public function update(Request $request, string $id)
    {
        $plan = AcademicPlan::findOrFail($id);

        $validated = $request->validate([
            'academic_program_id' => 'required|exists:academic_programs,id',
            'name' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $plan->update($validated);

        return redirect()
            ->route('academic-plans.index')
            ->with('success', 'Plan académico actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $plan = AcademicPlan::findOrFail($id);

        $plan->delete();

        return redirect()
            ->route('academic-plans.index')
            ->with('success', 'Plan académico eliminado correctamente.');
    }
}