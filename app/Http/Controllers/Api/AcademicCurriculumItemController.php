<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCurriculumItem;
use App\Models\AcademicLevel;
use App\Models\AcademicPlan;
use App\Models\AcademicUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCurriculumItemController extends Controller
{
    public function index()
    {
        $items = AcademicCurriculumItem::with([
            'academicPlan',
            'academicLevel',
            'academicUnit',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules($request),
            $this->messages()
        );

        $unit = AcademicUnit::findOrFail($validated['academic_unit_id']);

        // Tomar los valores de la unidad solo si no fueron proporcionados.
        $validated['credits'] ??= $unit->credits;
        $validated['hours'] ??= $unit->hours;
        $validated['is_required'] ??= true;

        $item = AcademicCurriculumItem::create($validated);

        return response()->json([
            'success' => true,
            'data' => $item->load([
                'academicPlan',
                'academicLevel',
                'academicUnit',
            ]),
        ], 201);
    }

    public function show($id)
    {
        $item = AcademicCurriculumItem::with([
            'academicPlan',
            'academicLevel',
            'academicUnit',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item = AcademicCurriculumItem::findOrFail($id);

        $validated = $request->validate(
            $this->rules($request, $item),
            $this->messages()
        );

        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => $item->fresh()->load([
                'academicPlan',
                'academicLevel',
                'academicUnit',
            ]),
        ]);
    }

    public function destroy($id)
    {
        $item = AcademicCurriculumItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Elemento curricular eliminado correctamente.',
        ], 200);
    }

    /**
     * Reglas compartidas por creación y actualización.
     * La combinación (plan, nivel, curso) debe ser única.
     */
    private function rules(Request $request, ?AcademicCurriculumItem $item = null): array
    {
        $unitUnique = Rule::unique('academic_curriculum_items', 'academic_unit_id')
            ->where('academic_plan_id', $request->input('academic_plan_id'))
            ->where('academic_level_id', $request->input('academic_level_id'));

        if ($item !== null) {
            $unitUnique->ignore($item->id);
        }

        return [
            'academic_plan_id' => [
                'required',
                'exists:academic_plans,id',
            ],
            'academic_level_id' => [
                'required',
                'exists:academic_levels,id',
                function ($attribute, $value, $fail) use ($request) {
                    $level = AcademicLevel::find($value);

                    if ($level && (int) $level->academic_plan_id !== (int) $request->input('academic_plan_id')) {
                        $fail('El nivel académico seleccionado no pertenece al plan académico indicado.');
                    }
                },
            ],
            'academic_unit_id' => [
                'required',
                'exists:academic_units,id',
                $unitUnique,
                function ($attribute, $value, $fail) use ($request) {
                    $plan = AcademicPlan::with('academicProgram')->find($request->input('academic_plan_id'));
                    $unit = AcademicUnit::find($value);

                    if (
                        $plan && $plan->academicProgram && $unit &&
                        (int) $plan->academicProgram->institution_id !== (int) $unit->institution_id
                    ) {
                        $fail('La unidad académica debe pertenecer a la institución del plan académico.');
                    }
                },
            ],
            'credits' => 'nullable|integer|min:0',
            'hours' => 'nullable|integer|min:0',
            'is_required' => 'boolean',
            'position' => 'nullable|integer|min:1',
        ];
    }

    private function messages(): array
    {
        return [
            'academic_plan_id.required' => 'El plan académico es obligatorio.',
            'academic_plan_id.exists' => 'El plan académico seleccionado no existe.',
            'academic_level_id.required' => 'El nivel académico es obligatorio.',
            'academic_level_id.exists' => 'El nivel académico seleccionado no existe.',
            'academic_unit_id.required' => 'La unidad académica es obligatoria.',
            'academic_unit_id.exists' => 'La unidad académica seleccionada no existe.',
            'academic_unit_id.unique' => 'Esta unidad académica ya está registrada en este nivel y plan.',
            'credits.integer' => 'Los créditos deben ser un número entero.',
            'credits.min' => 'Los créditos no pueden ser negativos.',
            'hours.integer' => 'Las horas deben ser un número entero.',
            'hours.min' => 'Las horas no pueden ser negativas.',
            'is_required.boolean' => 'El estado de obligatoriedad no es válido.',
            'position.integer' => 'La posición debe ser un número entero.',
            'position.min' => 'La posición debe ser como mínimo 1.',
        ];
    }
}
