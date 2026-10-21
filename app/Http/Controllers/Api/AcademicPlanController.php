<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;





class AcademicPlanController extends Controller
{
    public function index()
    {
        $plans = AcademicPlan::with('academicProgram')->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'academic_program_id' => [
                    'required',
                    'exists:academic_programs,id',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('academic_plans', 'name')
                        ->where(
                            'academic_program_id',
                            $request->academic_program_id
                        ),
                ],

                'version' => 'required|string|max:50',

                'start_date' => 'nullable|date',

                'end_date' => 'nullable|date|after_or_equal:start_date',

                'is_active' => 'boolean',
            ],
            [
                'academic_program_id.required' =>
                    'El programa académico es obligatorio.',

                'academic_program_id.exists' =>
                    'El programa académico seleccionado no existe.',

                'name.required' =>
                    'El nombre del plan académico es obligatorio.',

                'name.unique' =>
                    'Ya existe un plan académico con ese nombre para este programa.',

                'name.max' =>
                    'El nombre del plan académico no puede superar los 255 caracteres.',

                'version.required' =>
                    'La versión del plan académico es obligatoria.',

                'version.max' =>
                    'La versión no puede superar los 50 caracteres.',

                'start_date.date' =>
                    'La fecha de inicio debe ser una fecha válida.',

                'end_date.date' =>
                    'La fecha de fin debe ser una fecha válida.',

                'end_date.after_or_equal' =>
                    'La fecha de fin debe ser igual o posterior a la fecha de inicio.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        $plan = AcademicPlan::create($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ], 201);
    }

    public function show($id)
    {
        $plan = AcademicPlan::with('academicProgram')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = AcademicPlan::findOrFail($id);

        $validated = $request->validate(
            [
                'academic_program_id' => [
                    'required',
                    'exists:academic_programs,id',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('academic_plans', 'name')
                        ->where(
                            'academic_program_id',
                            $request->academic_program_id
                        )
                        ->ignore($plan->id),
                ],

                'version' => 'required|string|max:50',

                'start_date' => 'nullable|date',

                'end_date' => 'nullable|date|after_or_equal:start_date',

                'is_active' => 'boolean',
            ],
            [
                'academic_program_id.required' =>
                    'El programa académico es obligatorio.',

                'academic_program_id.exists' =>
                    'El programa académico seleccionado no existe.',

                'name.required' =>
                    'El nombre del plan académico es obligatorio.',

                'name.unique' =>
                    'Ya existe un plan académico con ese nombre para este programa.',

                'name.max' =>
                    'El nombre del plan académico no puede superar los 255 caracteres.',

                'version.required' =>
                    'La versión del plan académico es obligatoria.',

                'version.max' =>
                    'La versión no puede superar los 50 caracteres.',

                'start_date.date' =>
                    'La fecha de inicio debe ser una fecha válida.',

                'end_date.date' =>
                    'La fecha de fin debe ser una fecha válida.',

                'end_date.after_or_equal' =>
                    'La fecha de fin debe ser igual o posterior a la fecha de inicio.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        $plan->update($validated);

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

   
public function destroy($id)
{
    $plan = AcademicPlan::findOrFail($id);

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