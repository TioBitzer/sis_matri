<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index()
    {
        $people = Person::with('institution')->get();

        return response()->json([
            'success' => true,
            'data' => $people,
        ]);
    }
    public function store(Request $request)
{
    $validated = $request->validate([
        'institution_id' => 'required|exists:institutions,id',
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'document_type' => 'required|string|max:20',
        'document_number' => 'required|string|max:50',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:20',
        'birth_date' => 'nullable|date',
    ]);

    $person = Person::create($validated);

    return response()->json([
        'success' => true,
        'data' => $person,
    ], 201);
}
public function show($id)
{
    $person = Person::with('institution')->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => $person,
    ]);
}
public function update(Request $request, $id)
{
    $person = Person::findOrFail($id);

    $validated = $request->validate([
        'institution_id' => 'required|exists:institutions,id',
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'document_type' => 'required|string|max:20',
        'document_number' => 'required|string|max:50',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:20',
        'birth_date' => 'nullable|date',
    ]);

    $person->update($validated);

    return response()->json([
        'success' => true,
        'data' => $person,
    ]);
}
public function destroy($id)
{
    $person = Person::findOrFail($id);

    $person->delete();

    return response()->json([
        'success' => true,
        'message' => 'Persona eliminada correctamente',
    ]);
}
}