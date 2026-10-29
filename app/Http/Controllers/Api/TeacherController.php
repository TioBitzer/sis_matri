<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    /**
     * Aisla los docentes de la institución activa y valida también su persona.
     */
    private function scopedTeachers(Request $request): Builder
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        return Teacher::query()
            ->where('institution_id', $institutionId)
            ->whereHas('person', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            });
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->scopedTeachers($request)->with(['person', 'institution'])->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), $this->messages());

        // Asegura que la respuesta incluya el mismo valor predeterminado que la BD.
        $validated['is_active'] = $validated['is_active'] ?? true;

        $teacher = Teacher::create($validated);

        return response()->json([
            'success' => true,
            'data' => $teacher->load(['person', 'institution']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $teacher = $this->scopedTeachers($request)
            ->with(['person', 'institution'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $teacher,
        ]);
    }

    public function update(Request $request, $id)
    {
        $teacher = $this->scopedTeachers($request)->findOrFail($id);
        $validated = $request->validate($this->rules($request, $teacher), $this->messages());

        // La identidad y la institución de un docente con clases asignadas
        // no deben alterarse; sus asignaciones conservarían datos inconsistentes.
        if (
            $teacher->teachingAssignments()->exists() &&
            (
                (int) $teacher->person_id !== (int) $validated['person_id'] ||
                (int) $teacher->institution_id !== (int) $validated['institution_id']
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cambiar la persona o institución de un docente con asignaciones académicas.',
            ], 409);
        }

        $teacher->update($validated);

        return response()->json([
            'success' => true,
            'data' => $teacher->fresh()->load(['person', 'institution']),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $teacher = $this->scopedTeachers($request)->findOrFail($id);

        // La FK de teaching_assignments aplica cascadeOnDelete.
        // Evitamos borrar las asignaciones sin una decisión explícita.
        if ($teacher->teachingAssignments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el docente porque tiene asignaciones académicas.',
            ], 409);
        }

        $teacher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Profesor eliminado correctamente',
        ]);
    }

    private function rules(Request $request, ?Teacher $teacher = null): array
    {
        $institutionId = (int) $request->attributes->get('institution_id');

        $personUnique = Rule::unique('teachers', 'person_id');
        $codeUnique = Rule::unique('teachers', 'employee_code')
            ->where('institution_id', $institutionId);

        if ($teacher !== null) {
            $personUnique->ignore($teacher->id);
            $codeUnique->ignore($teacher->id);
        }

        return [
            'person_id' => [
                'required',
                'integer',
                'exists:people,id',
                $personUnique,
                function ($attribute, $value, $fail) use ($request, $institutionId) {
                    $person = Person::find($value);

                    if (
                        $person !== null &&
                        (int) $person->institution_id !== (int) $request->input('institution_id')
                    ) {
                        $fail('La persona seleccionada no pertenece a la institución indicada.');
                    }

                    if ($person !== null && (int) $person->institution_id !== $institutionId) {
                        $fail('La persona seleccionada no pertenece a la institución activa.');
                    }
                },
            ],
            'institution_id' => [
                'required',
                'integer',
                'exists:institutions,id',
                Rule::in([$institutionId]),
            ],
            'employee_code' => ['nullable', 'string', 'max:50', $codeUnique],
            'specialization' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'person_id.required' => 'La persona es obligatoria.',
            'person_id.exists' => 'La persona seleccionada no existe.',
            'person_id.unique' => 'Esta persona ya está registrada como docente.',
            'institution_id.required' => 'La institución es obligatoria.',
            'institution_id.exists' => 'La institución seleccionada no existe.',
            'employee_code.unique' => 'El código del docente ya está registrado en esta institución.',
            'employee_code.max' => 'El código del docente no puede superar los 50 caracteres.',
            'specialization.max' => 'La especialización no puede superar los 255 caracteres.',
            'is_active.boolean' => 'El estado del docente debe ser válido.',
        ];
    }
}
