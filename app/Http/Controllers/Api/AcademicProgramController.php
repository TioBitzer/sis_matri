<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicProgram;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicProgramController extends Controller
{
    public function index()
    {
        $programs = AcademicProgram::with('institution')->get();

        return response()->json([
            'success' => true,
            'data' => $programs,
        ]);
    }



public function store(Request $request)
{
    $validated = $request->validate(
        [
            'institution_id' => 'required|exists:institutions,id',

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_programs')
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'institution_id',
                            $request->institution_id
                        );
                    }),
            ],

            'code' => 'required|string|max:50',

            'description' => 'nullable|string',

            'is_active' => 'boolean',
        ],
        [
            'institution_id.required' =>
                'La institución es obligatoria.',

            'institution_id.exists' =>
                'La institución seleccionada no existe.',

            'name.required' =>
                'El nombre del programa es obligatorio.',

            'name.unique' =>
                'El nombre del programa ya está registrado para esta institución.',

            'name.max' =>
                'El nombre del programa no puede superar los 255 caracteres.',

            'code.required' =>
                'El código del programa es obligatorio.',

            'code.max' =>
                'El código del programa no puede superar los 50 caracteres.',

            'description.string' =>
                'La descripción debe ser un texto.',

            'is_active.boolean' =>
                'El estado debe ser válido.',
        ]
    );

    $program = AcademicProgram::create($validated);

    return response()->json([
        'success' => true,
        'data' => $program,
    ], 201);
}



public function show($id)
{
    $program = AcademicProgram::with('institution')->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $program,
    ]);
}

public function update(Request $request, $id)
{
    $program = AcademicProgram::findOrFail($id);

    $validated = $request->validate(
        [
            'institution_id' => 'required|exists:institutions,id',

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_programs')
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'institution_id',
                            $request->institution_id
                        );
                    })
                    ->ignore($program->id),
            ],

            'code' => 'required|string|max:50',

            'description' => 'nullable|string',

            'is_active' => 'boolean',
        ],
        [
            'institution_id.required' =>
                'La institución es obligatoria.',

            'institution_id.exists' =>
                'La institución seleccionada no existe.',

            'name.required' =>
                'El nombre del programa es obligatorio.',

            'name.unique' =>
                'El nombre del programa ya está registrado para esta institución.',

            'name.max' =>
                'El nombre del programa no puede superar los 255 caracteres.',

            'code.required' =>
                'El código del programa es obligatorio.',

            'code.max' =>
                'El código del programa no puede superar los 50 caracteres.',

            'description.string' =>
                'La descripción debe ser un texto.',

            'is_active.boolean' =>
                'El estado debe ser válido.',
        ]
    );

    $program->update($validated);

    return response()->json([
        'success' => true,
        'data' => $program,
    ]);
}

public function destroy($id)
{
    $program = AcademicProgram::findOrFail($id);

    if (
        $program->academicPlans()->exists() ||
        $program->academicRecords()->exists()
    ) {
        return response()->json([
            'success' => false,
            'message' => 'No se puede eliminar el programa académico porque tiene planes de estudio o historiales académicos asociados.',
        ], 409);
    }

    $program->delete();

    return response()->json([
        'success' => true,
        'message' => 'Programa académico eliminado correctamente.',
    ], 200);
}

}
