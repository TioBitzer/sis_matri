<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicLevelController extends Controller
{
    public function index()
    {
        $levels = AcademicLevel::with('academicPlan')->get();

        return response()->json([
            'success' => true,
            'data' => $levels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'academic_plan_id' => 'required|exists:academic_plans,id',

                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('academic_levels')
                        ->where('academic_plan_id', $request->academic_plan_id),
                ],

                'order' => [
                    'required',
                    'integer',
                    'min:1',
                    Rule::unique('academic_levels')
                        ->where('academic_plan_id', $request->academic_plan_id),
                ],

                'is_active' => 'boolean',
            ],
            [
                'academic_plan_id.required' =>
                    'El plan académico es obligatorio.',

                'academic_plan_id.exists' =>
                    'El plan académico seleccionado no existe.',

                'name.required' =>
                    'El nombre del nivel académico es obligatorio.',

                'name.max' =>
                    'El nombre del nivel académico no puede superar los 255 caracteres.',

                'name.unique' =>
                    'El nombre del nivel académico ya está registrado en este plan.',

                'order.required' =>
                    'El orden del nivel académico es obligatorio.',

                'order.integer' =>
                    'El orden debe ser un número entero.',

                'order.min' =>
                    'El orden debe ser como mínimo 1.',

                'order.unique' =>
                    'El orden ya está utilizado por otro nivel de este plan.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        // Hacer explícito el valor por defecto para la respuesta JSON.
        $validated['is_active'] ??= true;

        $level = AcademicLevel::create($validated);

        return response()->json([
            'success' => true,
            'data' => $level,
        ], 201);
    }

    public function show($id)
    {
        $level = AcademicLevel::with('academicPlan')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $level,
        ]);
    }

    public function update(Request $request, $id)
    {
        $level = AcademicLevel::findOrFail($id);

        $validated = $request->validate(
            [
                'academic_plan_id' => 'required|exists:academic_plans,id',

                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('academic_levels')
                        ->where('academic_plan_id', $request->academic_plan_id)
                        ->ignore($level->id),
                ],

                'order' => [
                    'required',
                    'integer',
                    'min:1',
                    Rule::unique('academic_levels')
                        ->where('academic_plan_id', $request->academic_plan_id)
                        ->ignore($level->id),
                ],

                'is_active' => 'boolean',
            ],
            [
                'academic_plan_id.required' =>
                    'El plan académico es obligatorio.',

                'academic_plan_id.exists' =>
                    'El plan académico seleccionado no existe.',

                'name.required' =>
                    'El nombre del nivel académico es obligatorio.',

                'name.max' =>
                    'El nombre del nivel académico no puede superar los 255 caracteres.',

                'name.unique' =>
                    'El nombre del nivel académico ya está registrado en este plan.',

                'order.required' =>
                    'El orden del nivel académico es obligatorio.',

                'order.integer' =>
                    'El orden debe ser un número entero.',

                'order.min' =>
                    'El orden debe ser como mínimo 1.',

                'order.unique' =>
                    'El orden ya está utilizado por otro nivel de este plan.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        $level->update($validated);

        return response()->json([
            'success' => true,
            'data' => $level,
        ]);
    }

    public function destroy($id)
    {
        $level = AcademicLevel::findOrFail($id);

        // Proteger los elementos de la malla curricular ante borrados en cascada.
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
