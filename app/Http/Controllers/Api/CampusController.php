<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampusController extends Controller
{
    public function index()
    {
        $campuses = Campus::with('institution')->get();

        return response()->json([
            'success' => true,
            'data' => $campuses,
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
                Rule::unique('campuses')
                    ->where(function ($query) use ($request) {
                        return $query->where(
                            'institution_id',
                            $request->institution_id
                        );
                    }),
            ],

            'code' => 'nullable|string|max:50',

            'address' => 'nullable|string|max:255',

            'is_active' => 'boolean',
        ],
        [
            'institution_id.required' =>
                'La institución es obligatoria.',

            'institution_id.exists' =>
                'La institución seleccionada no existe.',

            'name.required' =>
                'El nombre del campus es obligatorio.',

            'name.max' =>
                'El nombre del campus no puede superar los 255 caracteres.',

            'name.unique' =>
                'El nombre del campus ya está registrado en esta institución.',

            'code.string' =>
                'El código debe ser texto.',

            'code.max' =>
                'El código no puede superar los 50 caracteres.',

            'address.string' =>
                'La dirección debe ser texto.',

            'address.max' =>
                'La dirección no puede superar los 255 caracteres.',

            'is_active.boolean' =>
                'El estado debe ser válido.',
        ]
    );

    $campus = Campus::create($validated);

    return response()->json([
        'success' => true,
        'data' => $campus,
    ], 201);
}


public function show($id)
{
    $campus = Campus::with('institution')->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $campus,
    ]);
}

public function update(Request $request, $id)
{
    $campus = Campus::findOrFail($id);

    $validated = $request->validate([
        'institution_id' => 'required|exists:institutions,id',

        'name' => [
            'required',
            'string',
            'max:255',
            Rule::unique('campuses')
                ->where(function ($query) use ($request) {
                    return $query->where(
                        'institution_id',
                        $request->institution_id
                    );
                })
                ->ignore($campus->id),
        ],

        'code' => 'required|string|max:50',
        'address' => 'nullable|string|max:255',
        'is_active' => 'boolean',
    ]);

    $campus->update($validated);

    return response()->json([
        'success' => true,
        'data' => $campus,
    ]);
}

public function destroy($id)
{
    $campus = Campus::findOrFail($id);

    $campus->delete();

    return response()->json([
        'success' => true,
        'message' => 'Campus eliminado correctamente',
    ]);
}
}