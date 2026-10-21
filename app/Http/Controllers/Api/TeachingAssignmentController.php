<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicOffering;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeachingAssignmentController extends Controller
{
    private const RELATIONS = [
        'teacher.person',
        'academicOffering.academicUnit',
        'academicOffering.academicPeriod',
        'academicOffering.campus',
    ];

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => TeachingAssignment::with(self::RELATIONS)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request), $this->messages());
        $assignment = TeachingAssignment::create($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment->load(self::RELATIONS),
        ], 201);
    }

    public function show($id)
    {
        $assignment = TeachingAssignment::with(self::RELATIONS)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    public function update(Request $request, $id)
    {
        $assignment = TeachingAssignment::findOrFail($id);
        $validated = $request->validate($this->rules($request, $assignment), $this->messages());

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment->fresh()->load(self::RELATIONS),
        ]);
    }

    public function destroy($id)
    {
        $assignment = TeachingAssignment::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Asignación docente eliminada correctamente',
        ]);
    }

    private function rules(Request $request, ?TeachingAssignment $assignment = null): array
    {
        // No bloquear la edición del rol de una asignación histórica si luego
        // el docente o la oferta se desactivaron. Sí exigir activos al crear
        // una asignación nueva o cambiar cualquiera de sus dos vínculos.
        $changingLinks = $assignment === null
            || (int) $assignment->teacher_id !== (int) $request->input('teacher_id')
            || (int) $assignment->academic_offering_id !== (int) $request->input('academic_offering_id');

        return [
            'teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id',
                Rule::unique('teaching_assignments', 'teacher_id')
                    ->where('academic_offering_id', $request->input('academic_offering_id'))
                    ->ignore($assignment?->id),
                function ($attribute, $value, $fail) use ($changingLinks) {
                    $teacher = Teacher::find($value);
                    if ($changingLinks && $teacher && !$teacher->is_active) {
                        $fail('No se puede asignar un docente inactivo.');
                    }
                },
            ],
            'academic_offering_id' => [
                'required',
                'integer',
                'exists:academic_offerings,id',
                function ($attribute, $value, $fail) use ($request, $changingLinks) {
                    $offering = AcademicOffering::with(['academicUnit', 'academicPeriod', 'campus'])
                        ->find($value);
                    $teacher = Teacher::find($request->input('teacher_id'));

                    if (!$offering) {
                        return; // La regla exists genera el error correspondiente.
                    }

                    if ($changingLinks && !$offering->is_active) {
                        $fail('No se puede asignar un docente a una oferta académica inactiva.');
                    }

                    $institutionId = $offering->academicUnit?->institution_id;
                    if (
                        $institutionId === null
                        || (int) $offering->academicPeriod?->institution_id !== (int) $institutionId
                        || ($offering->campus !== null && (int) $offering->campus->institution_id !== (int) $institutionId)
                    ) {
                        $fail('La oferta académica debe tener curso, periodo y sede de la misma institución.');
                    }

                    if ($teacher && $institutionId !== null && (int) $teacher->institution_id !== (int) $institutionId) {
                        $fail('El docente y la oferta académica deben pertenecer a la misma institución.');
                    }
                },
            ],
            'role' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function messages(): array
    {
        return [
            'teacher_id.required' => 'El docente es obligatorio.',
            'teacher_id.integer' => 'El docente debe ser un identificador numérico.',
            'teacher_id.exists' => 'El docente seleccionado no existe.',
            'teacher_id.unique' => 'Este docente ya está asignado a esa oferta académica.',
            'academic_offering_id.required' => 'La oferta académica es obligatoria.',
            'academic_offering_id.integer' => 'La oferta debe ser un identificador numérico.',
            'academic_offering_id.exists' => 'La oferta académica seleccionada no existe.',
            'role.string' => 'El rol debe ser texto.',
            'role.max' => 'El rol no puede superar los 100 caracteres.',
        ];
    }
}
