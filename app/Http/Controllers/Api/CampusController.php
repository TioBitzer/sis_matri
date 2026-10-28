<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampusController extends Controller
{
    public function index(Request $request)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $campuses = Campus::with('institution')
            ->where('institution_id', $institutionId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $campuses,
        ]);
    }

    public function store(Request $request)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $validated = $request->validate(
            [
                'institution_id' => [
                    'required',
                    'integer',
                    'exists:institutions,id',
                    Rule::in([$institutionId]),
                ],
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('campuses')
                        ->where('institution_id', $institutionId),
                ],
                'code' => 'nullable|string|max:50',
                'address' => 'nullable|string|max:255',
                'is_active' => 'boolean',
            ],
            [
                'institution_id.required' => 'La institución es obligatoria.',
                'institution_id.exists' => 'La institución seleccionada no existe.',
                'institution_id.in' => 'No tienes autorización para usar esta institución.',
                'name.required' => 'El nombre del campus es obligatorio.',
                'name.max' => 'El nombre del campus no puede superar los 255 caracteres.',
                'name.unique' => 'El nombre del campus ya está registrado en esta institución.',
                'code.string' => 'El código debe ser texto.',
                'code.max' => 'El código no puede superar los 50 caracteres.',
                'address.string' => 'La dirección debe ser texto.',
                'address.max' => 'La dirección no puede superar los 255 caracteres.',
                'is_active.boolean' => 'El estado debe ser válido.',
            ]
        );

        // La institución se determina exclusivamente por el middleware.
        $validated['institution_id'] = $institutionId;
        $campus = Campus::create($validated);

        return response()->json([
            'success' => true,
            'data' => $campus,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $campus = Campus::with('institution')
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $campus,
        ]);
    }

    public function update(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $campus = Campus::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $validated = $request->validate([
            'institution_id' => [
                'required',
                'integer',
                'exists:institutions,id',
                Rule::in([$institutionId]),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campuses')
                    ->where('institution_id', $institutionId)
                    ->ignore($campus->id),
            ],
            'code' => 'required|string|max:50',
            'address' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ], [
            'institution_id.in' => 'No tienes autorización para usar esta institución.',
        ]);

        // No permitir cambios de institución mediante el cuerpo de la solicitud.
        $validated['institution_id'] = $institutionId;
        $campus->update($validated);

        return response()->json([
            'success' => true,
            'data' => $campus,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $campus = Campus::query()
            ->where('institution_id', $institutionId)
            ->findOrFail($id);

        $campus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campus eliminado correctamente',
        ]);
    }
}
