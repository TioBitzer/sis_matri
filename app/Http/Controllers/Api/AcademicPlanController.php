<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicPlanController extends Controller
{
    /**
     * La institución autorizada proviene de institution.access,
     * no de parámetros enviados por el cliente.
     */
    private function institutionId(Request $request): int
    {
        return (int) $request->attributes->get('institution_id');
    }

    private function scopedPlans(int $institutionId)
    {
        return AcademicPlan::query()->whereHas('academicProgram', function ($query) use ($institutionId) {
            $query->where('institution_id', $institutionId);
        });
    }

    private function rules(Request $request, int $institutionId, ?int $ignoreId = null): array
    {
        $nameRule = Rule::unique('academic_plans', 'name')
            ->where('academic_program_id', $request->input('academic_program_id'));

        if ($ignoreId !== null) {
            $nameRule->ignore($ignoreId);
        }

        return [
            'academic_program_id' => [
                'required',
                'integer',
                Rule::exists('academic_programs', 'id')
                    ->where('institution_id', $institutionId),
            ],
            'name' => ['required', 'string', 'max:255', $nameRule],
            'version' => ['required', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'academic_program_id.required' => 'El programa académico es obligatorio.',
            'academic_program_id.exists' => 'El programa académico seleccionado no pertenece a esta institución o no existe.',
            'name.required' => 'El nombre del plan académico es obligatorio.',
            'name.unique' => 'Ya existe un plan académico con ese nombre para este programa.',
            'name.max' => 'El nombre del plan académico no puede superar los 255 caracteres.',
            'version.required' => 'La versión del plan académico es obligatoria.',
            'version.max' => 'La versión no puede superar los 50 caracteres.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'is_active.boolean' => 'El estado debe ser válido.',
        ];
    }

    public function index(Request $request)
    {
        $plans = $this->scopedPlans($this->institutionId($request))
            ->with('academicProgram')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules($request, $this->institutionId($request)),
            $this->messages()
        );

        $plan = AcademicPlan::create($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $plan = $this->scopedPlans($this->institutionId($request))
            ->with('academicProgram')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = $this->institutionId($request);
        $plan = $this->scopedPlans($institutionId)->findOrFail($id);

        $validated = $request->validate(
            $this->rules($request, $institutionId, $plan->id),
            $this->messages()
        );

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'data' => $plan->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $plan = $this->scopedPlans($this->institutionId($request))
            ->findOrFail($id);

        if (
            $plan->academicRecords()->exists() ||
            $plan->curriculumItems()->exists() ||
            $plan->academicLevels()->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el plan académico porque tiene registros académicos, elementos curriculares o niveles asociados.',
            ], 409);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan académico eliminado correctamente',
        ], 200);
    }
}
