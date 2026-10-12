<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class AcademicPeriodController extends Controller

{
    public function index()
    {
        $periods = AcademicPeriod::with('institution')->get();

        return response()->json([
            'success' => true,
            'data' => $periods,
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
                Rule::unique('academic_periods')
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'institution_id',
                            $request->institution_id
                        );
                    }),
            ],

            'start_date' => 'required|date',

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'is_active' => 'boolean',
        ],
        [
            'institution_id.required' =>
                'La institución es obligatoria.',

            'institution_id.exists' =>
                'La institución seleccionada no existe.',

            'name.required' =>
                'El nombre del período académico es obligatorio.',

            'name.max' =>
                'El nombre del período académico no puede superar los 255 caracteres.',

            'name.unique' =>
                'El nombre del período académico ya está registrado en esta institución.',

            'start_date.required' =>
                'La fecha de inicio es obligatoria.',

            'start_date.date' =>
                'La fecha de inicio debe ser una fecha válida.',

            'end_date.required' =>
                'La fecha de fin es obligatoria.',

            'end_date.date' =>
                'La fecha de fin debe ser una fecha válida.',

            'end_date.after_or_equal' =>
                'La fecha de fin debe ser igual o posterior a la fecha de inicio.',

            'is_active.boolean' =>
                'El estado debe ser válido.',
        ]
    );

    $period = AcademicPeriod::create($validated);

    return response()->json([
        'success' => true,
        'data' => $period,
    ], 201);
}


public function show($id)
{
    $period = AcademicPeriod::with('institution')->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $period,
    ]);
}

public function update(Request $request, $id)
{
    $period = AcademicPeriod::findOrFail($id);

    $validated = $request->validate(
        [
            'institution_id' => 'required|exists:institutions,id',

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('academic_periods')
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'institution_id',
                            $request->institution_id
                        );
                    })
                    ->ignore($period->id),
            ],

            'start_date' => 'required|date',

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'is_active' => 'boolean',
        ],
        [
            'institution_id.required' =>
                'La institución es obligatoria.',

            'institution_id.exists' =>
                'La institución seleccionada no existe.',

            'name.required' =>
                'El nombre del período académico es obligatorio.',

            'name.max' =>
                'El nombre del período académico no puede superar los 255 caracteres.',

            'name.unique' =>
                'El nombre del período académico ya está registrado en esta institución.',

            'start_date.required' =>
                'La fecha de inicio es obligatoria.',

            'start_date.date' =>
                'La fecha de inicio debe ser una fecha válida.',

            'end_date.required' =>
                'La fecha de fin es obligatoria.',

            'end_date.date' =>
                'La fecha de fin debe ser una fecha válida.',

            'end_date.after_or_equal' =>
                'La fecha de fin debe ser igual o posterior a la fecha de inicio.',

            'is_active.boolean' =>
                'El estado debe ser válido.',
        ]
    );

    $period->update($validated);

    return response()->json([
        'success' => true,
        'data' => $period,
    ]);
}


public function destroy($id)
{
    $period = AcademicPeriod::findOrFail($id);

    $period->delete();

    return response()->json([
        'success' => true,
        'message' => 'Período académico eliminado correctamente',
    ]);
}
}