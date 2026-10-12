<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicOffering;
use Illuminate\Http\Request;

class AcademicOfferingController extends Controller
{
    public function index()
    {
        $offerings = AcademicOffering::with([
            'academicUnit',
            'academicPeriod',
            'campus',
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $offerings,
        ]);
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'academic_unit_id' => 'required|exists:academic_units,id',
        'academic_period_id' => 'required|exists:academic_periods,id',
        'campus_id' => 'required|exists:campuses,id',
        'section' => 'required|string|max:50',
        'capacity' => 'required|integer|min:1',
        'is_active' => 'boolean',
    ]);

    $offering = AcademicOffering::create($validated);

    return response()->json([
        'success' => true,
        'data' => $offering,
    ], 201);
}
public function show($id)
{
    $offering = AcademicOffering::with([
        'academicUnit',
        'academicPeriod',
        'campus',
    ])->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $offering,
    ]);
}
public function update(Request $request, $id)
{
    $offering = AcademicOffering::findOrFail($id);

    $validated = $request->validate([
        'academic_unit_id' => 'required|exists:academic_units,id',
        'academic_period_id' => 'required|exists:academic_periods,id',
        'campus_id' => 'required|exists:campuses,id',
        'section' => 'required|string|max:50',
        'capacity' => 'required|integer|min:1',
        'is_active' => 'boolean',
    ]);

    $offering->update($validated);

    return response()->json([
        'success' => true,
        'data' => $offering,
    ]);
}
public function destroy($id)
{
    $offering = AcademicOffering::findOrFail($id);

    $offering->delete();

    return response()->json([
        'success' => true,
        'message' => 'Oferta académica eliminada correctamente',
    ]);
}
}