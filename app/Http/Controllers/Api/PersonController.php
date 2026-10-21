<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Person::with('institution')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), $this->messages());
        $person = Person::create($validated);

        return response()->json([
            'success' => true,
            'data' => $person->fresh()->load('institution'),
        ], 201);
    }

    public function show($id)
    {
        $person = Person::with('institution')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $person,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $person = Person::findOrFail($id);
        $validated = $request->validate($this->rules($request, $person), $this->messages());

        // Cambiar de institución a una persona vinculada a alumnos o docentes
        // puede dejar datos académicos asociados a instituciones distintas.
        if (
            (int) $person->institution_id !== (int) $validated['institution_id'] &&
            ($person->student()->exists() || $person->teacher()->exists())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar de institución a una persona vinculada a un estudiante o docente.',
            ], 409);
        }

        $person->update($validated);

        return response()->json([
            'success' => true,
            'data' => $person->fresh()->load('institution'),
        ], 200);
    }

    public function destroy($id)
    {
        $person = Person::findOrFail($id);

        // La FK de students/person_id puede aplicar eliminación en cascada.
        // Protegemos explícitamente los registros académicos y docentes.
        if ($person->student()->exists() || $person->teacher()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la persona porque tiene un estudiante o docente asociado.',
            ], 409);
        }

        $person->delete();

        return response()->json([
            'success' => true,
            'message' => 'Persona eliminada correctamente',
        ], 200);
    }

    private function rules(Request $request, ?Person $person = null): array
    {
        $documentRule = Rule::unique('people', 'document_number')
            ->where('institution_id', $request->input('institution_id'));

        if ($person !== null) {
            $documentRule->ignore($person->id);
        }

        return [
            'institution_id' => ['required', 'integer', 'exists:institutions,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:20'],
            'document_number' => ['required', 'string', 'max:50', $documentRule],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'birth_date' => ['nullable', 'date'],
        ];
    }

    private function messages(): array
    {
        return [
            'institution_id.required' => 'La institución es obligatoria.',
            'institution_id.exists' => 'La institución seleccionada no existe.',
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'El apellido es obligatorio.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'document_number.unique' => 'Ya existe una persona con ese número de documento en esta institución.',
            'email.email' => 'El correo electrónico no es válido.',
            'birth_date.date' => 'La fecha de nacimiento no es válida.',
        ];
    }
}
