<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index()
    {
        $institutions = Institution::all();

        return response()->json([
            'success' => true,
            'data' => $institutions,
        ]);
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
      'code' => 'required|string|max:50|unique:institutions,code',
        'type' => 'required|string|max:50',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
    ]);

    $institution = Institution::create($validated);

    return response()->json([
        'success' => true,
        'data' => $institution,
    ], 201);
}
public function show($id)
{
    $institution = Institution::findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $institution,
    ]);
}
public function update(Request $request, $id)
{
    $institution = Institution::findOrFail($id);

    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'code' => 'required|string|max:50|unique:institutions,code,' . $id,
        'type' => 'required|string|max:50',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
    ]);

    $institution->update($validated);

    return response()->json([
        'success' => true,
        'data' => $institution,
    ]);
}
public function destroy($id)
{
    $institution = Institution::findOrFail($id);

    $institution->delete();

    return response()->json([
        'success' => true,
        'message' => 'Institución eliminada correctamente',
    ]);
}
}