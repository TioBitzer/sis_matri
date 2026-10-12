<?php

namespace App\Http\Controllers;

use App\Models\AcademicCurriculumItem;
use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicUnit;
use Illuminate\Http\Request;

class AcademicCurriculumItemController extends Controller
{
    public function index()
    {
        $items = AcademicCurriculumItem::with([
            'academicPlan.academicProgram',
            'academicLevel',
            'academicUnit'
        ])
        ->orderBy('academic_plan_id')
        ->orderBy('academic_level_id')
        ->orderBy('position')
        ->get();

        return view('academic-curriculum-items.index', compact('items'));
    }

    public function create()
    {
        $plans = AcademicPlan::where('is_active', true)
            ->with('academicProgram')
            ->orderBy('name')
            ->get();

        $levels = AcademicLevel::where('is_active', true)
            ->orderBy('academic_plan_id')
            ->orderBy('order')
            ->get();

        $units = AcademicUnit::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'academic-curriculum-items.create',
            compact('plans', 'levels', 'units')
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'academic_level_id' => 'required|exists:academic_levels,id',
            'academic_unit_id' => 'required|exists:academic_units,id',
            'credits' => 'required|integer|min:0',
            'hours' => 'required|integer|min:0',
            'is_required' => 'boolean',
            'position' => 'required|integer|min:1',
        ]);

        $validated['is_required'] = $request->boolean('is_required');

        AcademicCurriculumItem::create($validated);

        return redirect()
            ->route('academic-curriculum-items.index')
            ->with('success', 'Elemento curricular creado correctamente.');
    }

    public function show(string $id)
    {
        $item = AcademicCurriculumItem::with([
            'academicPlan.academicProgram',
            'academicLevel',
            'academicUnit'
        ])->findOrFail($id);

        return view('academic-curriculum-items.show', compact('item'));
    }

    public function edit(string $id)
    {
        $item = AcademicCurriculumItem::findOrFail($id);

        $plans = AcademicPlan::where('is_active', true)
            ->with('academicProgram')
            ->orderBy('name')
            ->get();

        $levels = AcademicLevel::where('is_active', true)
            ->orderBy('academic_plan_id')
            ->orderBy('order')
            ->get();

        $units = AcademicUnit::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view(
            'academic-curriculum-items.edit',
            compact('item', 'plans', 'levels', 'units')
        );
    }

    public function update(Request $request, string $id)
    {
        $item = AcademicCurriculumItem::findOrFail($id);

        $validated = $request->validate([
            'academic_plan_id' => 'required|exists:academic_plans,id',
            'academic_level_id' => 'required|exists:academic_levels,id',
            'academic_unit_id' => 'required|exists:academic_units,id',
            'credits' => 'required|integer|min:0',
            'hours' => 'required|integer|min:0',
            'is_required' => 'boolean',
            'position' => 'required|integer|min:1',
        ]);

        $validated['is_required'] = $request->boolean('is_required');

        $item->update($validated);

        return redirect()
            ->route('academic-curriculum-items.index')
            ->with('success', 'Elemento curricular actualizado correctamente.');
    }

    public function destroy(string $id)
    {
        $item = AcademicCurriculumItem::findOrFail($id);

        $item->delete();

        return redirect()
            ->route('academic-curriculum-items.index')
            ->with('success', 'Elemento curricular eliminado correctamente.');
    }
}