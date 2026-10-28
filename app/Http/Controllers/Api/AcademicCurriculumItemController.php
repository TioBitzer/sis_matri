<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCurriculumItem;
use App\Models\AcademicUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicCurriculumItemController extends Controller
{
    /**
     * ID validado por el middleware institution.access.
     * Nunca confiar en una institución proporcionada en el cuerpo de la petición.
     */
    private function institutionId(Request $request): int
    {
        return (int) $request->attributes->get('institution_id');
    }

    /**
     * AcademicCurriculumItem -> AcademicPlan -> AcademicProgram -> Institution.
     * Todas las consultas por ID utilizan este scope para no exponer otros tenants.
     */
    private function scopedItems(int $institutionId)
    {
        return AcademicCurriculumItem::query()
            ->whereHas('academicPlan.academicProgram', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            });
    }

    public function index(Request $request)
    {
        $items = $this->scopedItems($this->institutionId($request))
            ->with(['academicPlan', 'academicLevel', 'academicUnit'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules($request, $this->institutionId($request)),
            $this->messages()
        );

        $unit = AcademicUnit::findOrFail($validated['academic_unit_id']);

        // Mantener el comportamiento original: copiar créditos y horas si se omiten.
        $validated['credits'] ??= $unit->credits;
        $validated['hours'] ??= $unit->hours;
        $validated['is_required'] ??= true;

        $item = AcademicCurriculumItem::create($validated);

        return response()->json([
            'success' => true,
            'data' => $item->load(['academicPlan', 'academicLevel', 'academicUnit']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $item = $this->scopedItems($this->institutionId($request))
            ->with(['academicPlan', 'academicLevel', 'academicUnit'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $item = $this->scopedItems($institutionId)->findOrFail($id);

        $validated = $request->validate(
            $this->rules($request, $institutionId, $item),
            $this->messages()
        );

        $item->update($validated);

        return response()->json([
            'success' => true,
            'data' => $item->fresh()->load(['academicPlan', 'academicLevel', 'academicUnit']),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $item = $this->scopedItems($this->institutionId($request))->findOrFail($id);
        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Elemento curricular eliminado correctamente.',
        ], 200);
    }

    /**
     * El plan, nivel y curso deben pertenecer a la institución autenticada.
     * El nivel debe pertenecer al plan solicitado y la combinación debe ser única.
     */
    private function rules(Request $request, int $institutionId, ?AcademicCurriculumItem $item = null): array
    {
        $planId = $request->input('academic_plan_id');
        $levelId = $request->input('academic_level_id');

        $unitUnique = Rule::unique('academic_curriculum_items', 'academic_unit_id')
            ->where('academic_plan_id', $planId)
            ->where('academic_level_id', $levelId);

        if ($item !== null) {
            $unitUnique->ignore($item->id);
        }

        return [
            'academic_plan_id' => [
                'required',
                'integer',
                Rule::exists('academic_plans', 'id')
                    ->where(function ($query) use ($institutionId) {
                        $query->whereIn('academic_program_id', function ($subquery) use ($institutionId) {
                            $subquery->select('id')
                                ->from('academic_programs')
                                ->where('institution_id', $institutionId);
                        });
                    }),
            ],
            'academic_level_id' => [
                'required',
                'integer',
                Rule::exists('academic_levels', 'id')
                    ->where('academic_plan_id', $planId),
            ],
            'academic_unit_id' => [
                'required',
                'integer',
                Rule::exists('academic_units', 'id')
                    ->where('institution_id', $institutionId),
                $unitUnique,
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
            'academic_plan_id.exists' => 'El plan académico seleccionado no existe o no pertenece a esta institución.',
            'academic_level_id.required' => 'El nivel académico es obligatorio.',
            'academic_level_id.exists' => 'El nivel académico seleccionado no existe o no pertenece al plan académico indicado.',
            'academic_unit_id.required' => 'La unidad académica es obligatoria.',
            'academic_unit_id.exists' => 'La unidad académica seleccionada no existe o no pertenece a esta institución.',
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
