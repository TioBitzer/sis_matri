<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;




class AcademicUnitController extends Controller
{
    public function index()
    {
        $units = AcademicUnit::with('institution')->get();

        return response()->json([
            'success' => true,
            'data' => $units,
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
                    Rule::unique('academic_units')
                        ->where(function ($query) use ($request) {
                            return $query->where(
                                'institution_id',
                                $request->institution_id
                            );
                        }),
                ],

                'code' => 'nullable|string|max:50',

                'description' => 'nullable|string',

                'unit_type' => 'nullable|string|max:255',

                'credits' => 'nullable|integer|min:0',

                'hours' => 'nullable|integer|min:0',

                'is_active' => 'boolean',
            ],
            [
                'institution_id.required' =>
                    'La institución es obligatoria.',

                'institution_id.exists' =>
                    'La institución seleccionada no existe.',

                'name.required' =>
                    'El nombre de la unidad académica es obligatorio.',

                'name.max' =>
                    'El nombre de la unidad académica no puede superar los 255 caracteres.',

                'name.unique' =>
                    'El nombre de la unidad académica ya está registrado en esta institución.',

                'code.string' =>
                    'El código debe ser texto.',

                'code.max' =>
                    'El código no puede superar los 50 caracteres.',

                'description.string' =>
                    'La descripción debe ser texto.',

                'unit_type.string' =>
                    'El tipo de unidad académica debe ser texto.',

                'unit_type.max' =>
                    'El tipo de unidad académica no puede superar los 255 caracteres.',

                'credits.integer' =>
                    'Los créditos deben ser un número entero.',

                'credits.min' =>
                    'Los créditos no pueden ser negativos.',

                'hours.integer' =>
                    'Las horas deben ser un número entero.',

                'hours.min' =>
                    'Las horas no pueden ser negativas.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        $unit = AcademicUnit::create($validated);

        return response()->json([
            'success' => true,
            'data' => $unit,
        ], 201);
    }

    public function show($id)
    {
        $unit = AcademicUnit::with('institution')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $unit,
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = AcademicUnit::findOrFail($id);

        $validated = $request->validate(
            [
                'institution_id' => 'required|exists:institutions,id',

                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('academic_units')
                        ->where(function ($query) use ($request) {
                            return $query->where(
                                'institution_id',
                                $request->institution_id
                            );
                        })
                        ->ignore($unit->id),
                ],

                'code' => 'nullable|string|max:50',

                'description' => 'nullable|string',

                'unit_type' => 'nullable|string|max:255',

                'credits' => 'nullable|integer|min:0',

                'hours' => 'nullable|integer|min:0',

                'is_active' => 'boolean',
            ],
            [
                'institution_id.required' =>
                    'La institución es obligatoria.',

                'institution_id.exists' =>
                    'La institución seleccionada no existe.',

                'name.required' =>
                    'El nombre de la unidad académica es obligatorio.',

                'name.max' =>
                    'El nombre de la unidad académica no puede superar los 255 caracteres.',

                'name.unique' =>
                    'El nombre de la unidad académica ya está registrado en esta institución.',

                'code.string' =>
                    'El código debe ser texto.',

                'code.max' =>
                    'El código no puede superar los 50 caracteres.',

                'description.string' =>
                    'La descripción debe ser texto.',

                'unit_type.string' =>
                    'El tipo de unidad académica debe ser texto.',

                'unit_type.max' =>
                    'El tipo de unidad académica no puede superar los 255 caracteres.',

                'credits.integer' =>
                    'Los créditos deben ser un número entero.',

                'credits.min' =>
                    'Los créditos no pueden ser negativos.',

                'hours.integer' =>
                    'Las horas deben ser un número entero.',

                'hours.min' =>
                    'Las horas no pueden ser negativas.',

                'is_active.boolean' =>
                    'El estado debe ser válido.',
            ]
        );

        $unit->update($validated);

        return response()->json([
            'success' => true,
            'data' => $unit,
        ]);
    }

    public function destroy($id)
    {
        $unit = AcademicUnit::findOrFail($id);

        $unit->delete();

        return response()->json([
            'success' => true,
            'message' => 'Unidad académica eliminada correctamente',
        ]);
    }
}
