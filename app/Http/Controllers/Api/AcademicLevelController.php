<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicLevelController extends Controller
{
    /**
     * Contexto institucional verificado por auth:sanctum + institution.access.
     * Nunca se acepta el tenant desde el cuerpo de la solicitud.
     */
    private function institutionId(Request $request): int
    {
        return (int) $request->attributes->get('institution_id');
    }

    /**
     * AcademicLevel -> AcademicPlan -> AcademicProgram -> Institution.
     */
    private function scopedLevels(int $institutionId)
    {
        return AcademicLevel::query()
            ->whereHas('academicPlan.academicProgram', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            });
    }

    /**
     * El plan seleccionado debe pertenecer a la institución autorizada.
     */
    private function rules(Request $request, int $institutionId, ?int $ignoreId = null): array
    {
        $planId = $request->input('academic_plan_id');

        $nameRule = Rule::unique('academic_levels', 'name')
            ->where('academic_plan_id', $planId);

        $orderRule = Rule::unique('academic_levels', 'order')
            ->where('academic_plan_id', $planId);

        if ($ignoreId !== null) {
            $nameRule->ignore($ignoreId);
            $orderRule->ignore($ignoreId);
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
            'name' => ['required', 'string', 'max:255', $nameRule],
            'order' => ['required', 'integer', 'min:1', $orderRule],
            'is_active' => ['boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'academic_plan_id.required' => 'El plan académico es obligatorio.',
            'academic_plan_id.exists' => 'El plan académico seleccionado no existe o no pertenece a esta institución.',
            'name.required' => 'El nombre del nivel académico es obligatorio.',
            'name.max' => 'El nombre del nivel académico no puede superar los 255 caracteres.',
            'name.unique' => 'El nombre del nivel académico ya está registrado en este plan.',
            'order.required' => 'El orden del nivel académico es obligatorio.',
            'order.integer' => 'El orden debe ser un número entero.',
            'order.min' => 'El orden debe ser como mínimo 1.',
            'order.unique' => 'El orden ya está utilizado por otro nivel de este plan.',
            'is_active.boolean' => 'El estado debe ser válido.',
        ];
    }

    public function index(Request $request)
    {
        $levels = $this->scopedLevels($this->institutionId($request))
            ->with('academicPlan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules($request, $this->institutionId($request)),
            $this->messages()
        );

        $validated['is_active'] ??= true;
        $level = AcademicLevel::create($validated);

        return response()->json([
            'success' => true,
            'data' => $level,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $level = $this->scopedLevels($this->institutionId($request))
            ->with('academicPlan')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $level,
        ]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $level = $this->scopedLevels($institutionId)->findOrFail($id);

        $validated = $request->validate(
            $this->rules($request, $institutionId, $level->id),
            $this->messages()
        );

        $level->update($validated);

        return response()->json([
            'success' => true,
            'data' => $level->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $level = $this->scopedLevels($this->institutionId($request))
            ->findOrFail($id);

        // Conservar las restricciones previas ante borrados en cascada.
        if ($level->curriculumItems()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el nivel académico porque tiene cursos asociados en la malla curricular.',
            ], 409);
        }

        $level->delete();

        return response()->json([
            'success' => true,
            'message' => 'Nivel académico eliminado correctamente',
        ], 200);
    }
}
